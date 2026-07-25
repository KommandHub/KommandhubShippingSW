<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\TerminalAfrica;

use Kommandhub\ShippingSW\Model\Carrier\Carrier;
use Kommandhub\ShippingSW\Model\Carrier\CarrierCollection;
use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\Shipment\Label;
use Kommandhub\ShippingSW\Model\Shipment\LabelFormat;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEvent;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEventCollection;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use Kommandhub\ShippingSW\Provider\Exception\ProviderException;

/**
 * The ONLY place TShip's payload shape is known. Terminal Africa is ID-based:
 * addresses (AD-…) and parcels (PC-…) are created first and referenced by id in
 * the rates call (RT-…), which is arranged into a shipment (SH-…). This mapper
 * builds each request body and parses each response id; the adapter owns the
 * ordering, id reuse, and I/O.
 *
 * NB: field names reflect the documented TShip v1 shape (docs.terminal.africa).
 * Where the live API differs, correct it here and only here.
 */
final class TShipMapper
{
    public const PROVIDER_KEY = 'terminal_africa';

    // --- Address (POST /addresses -> AD-…) -----------------------------------

    /**
     * create-address requires city, country (ISO-2), state.
     *
     * @return array<string, mixed>
     */
    public function addressCreatePayload(Address $address): array
    {
        return array_filter([
            'city' => $address->city,
            'country' => $address->countryCode,
            'state' => $address->state ?? $address->city,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'zip' => $address->postalCode,
            'name' => $address->name,
            'phone' => $address->phone,
            'email' => $address->email,
        ], static fn ($v): bool => null !== $v);
    }

    /**
     * @param array<string, mixed> $response
     */
    public function addressId(array $response): string
    {
        $data = $this->data($response);

        return (string) ($data['address_id'] ?? throw new ProviderException('TShip address response missing address_id.'));
    }

    // --- Parcel (POST /parcels -> PC-…) --------------------------------------

    /**
     * create-parcel requires items, packaging, weight_unit. We send one
     * synthetic item carrying the cart's weight/declared value; packaging is the
     * merchant's default packaging id (from provider config) when set.
     *
     * @return array<string, mixed>
     */
    public function parcelCreatePayload(RateRequest $request, ?string $packagingId): array
    {
        $payload = [
            'weight_unit' => 'kg',
            'currency' => $request->currency->value,
            'items' => [[
                'name' => 'Order items',
                'description' => 'Order items',
                'quantity' => 1,
                'value' => $request->declaredValue?->major() ?? '0',
                'weight' => $request->weight->kilograms(),
            ]],
        ];

        if (null !== $packagingId && '' !== $packagingId) {
            $payload['packaging'] = $packagingId;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $response
     */
    public function parcelId(array $response): string
    {
        $data = $this->data($response);

        return (string) ($data['parcel_id'] ?? throw new ProviderException('TShip parcel response missing parcel_id.'));
    }

    // --- Rates (POST /rates/multi/shipment) ----------------------------------

    /**
     * @param list<string> $parcelIds
     *
     * @return array<string, mixed>
     */
    public function ratesForShipmentPayload(string $pickupAddressId, string $deliveryAddressId, array $parcelIds, Currency $currency): array
    {
        return [
            'pickup_address' => $pickupAddressId,
            'delivery_address' => $deliveryAddressId,
            'parcels' => $parcelIds,
            'currency' => $currency->value,
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    public function toRateQuotes(array $response, Currency $currency): RateQuoteCollection
    {
        $rates = $this->dataList($response);

        $quotes = [];
        foreach ($rates as $rate) {
            $quotes[] = new RateQuote(
                providerKey: self::PROVIDER_KEY,
                serviceCode: (string) $rate['rate_id'],
                serviceName: trim(($rate['carrier_name'] ?? 'Terminal') . ' ' . ($rate['service_name'] ?? '')),
                amount: Money::fromMajor((string) $rate['amount'], $this->rateCurrency($rate, $currency)),
                estimatedDaysMin: isset($rate['delivery_days_min']) ? (int) $rate['delivery_days_min'] : null,
                estimatedDaysMax: isset($rate['delivery_days_max']) ? (int) $rate['delivery_days_max'] : null,
                carrierName: isset($rate['carrier_name']) ? (string) $rate['carrier_name'] : null,
                carrierCode: isset($rate['carrier_slug']) ? (string) $rate['carrier_slug'] : null,
            );
        }

        return new RateQuoteCollection(...$quotes);
    }

    // --- Carriers (GET /carriers) --------------------------------------------

    /**
     * @param array<string, mixed> $response
     */
    public function toCarriers(array $response): CarrierCollection
    {
        $list = $this->dataList($response);

        $carriers = [];
        foreach ($list as $carrier) {
            $code = $carrier['carrier_slug'] ?? $carrier['slug'] ?? $carrier['id'] ?? null;
            if (null === $code) {
                continue;
            }
            $carriers[] = new Carrier(
                code: (string) $code,
                name: (string) ($carrier['name'] ?? $carrier['carrier_name'] ?? $code),
                logoUrl: isset($carrier['logo']) ? (string) $carrier['logo'] : null,
            );
        }

        return new CarrierCollection(...$carriers);
    }

    // --- Arrange / create shipment (POST /shipments/pickup -> SH-…) -----------

    /**
     * Arrange pickup+delivery for a chosen rate. rate_id carries the shipment
     * context server-side, so the addresses/parcel created for rating are reused
     * — no re-creation at booking.
     *
     * @return array<string, mixed>
     */
    public function arrangePayload(string $rateId, string $reference, string $idempotencyKey): array
    {
        return [
            'rate_id' => $rateId,
            'reference' => $reference,
            // TShip has no idempotency header; carry our key as metadata so a
            // duplicate is detectable. Real dedup is the gateway's unique key.
            'metadata' => ['idempotency_key' => $idempotencyKey],
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    public function toShipment(array $response, string $serviceCode): Shipment
    {
        $data = $this->data($response);

        return new Shipment(
            providerKey: self::PROVIDER_KEY,
            providerShipmentId: (string) ($data['shipment_id'] ?? $data['id'] ?? throw new ProviderException('TShip shipment response missing shipment_id.')),
            serviceCode: $serviceCode,
            status: $this->mapStatus((string) ($data['status'] ?? 'pending')),
            trackingNumber: isset($data['carrier_tracking_number']) ? (string) $data['carrier_tracking_number'] : null,
            trackingUrl: isset($data['tracking_url']) ? (string) $data['tracking_url'] : null,
            createdAt: new \DateTimeImmutable(),
        );
    }

    // --- Label (GET /shipments/{shipment_id}) --------------------------------

    /**
     * @param array<string, mixed> $response
     */
    public function toLabel(array $response): Label
    {
        $data = $this->data($response);
        $url = $data['waybill_url'] ?? $data['label_url'] ?? null;

        if (null === $url) {
            throw new ProviderException('TShip label response missing waybill_url.');
        }

        return new Label(LabelFormat::PDF, url: (string) $url);
    }

    // --- Tracking (GET /shipments/track/{shipment_id}) -----------------------

    /**
     * @param array<string, mixed> $response
     */
    public function toTrackingEvents(array $response, string $stampTrackingNumber): TrackingEventCollection
    {
        $data = $this->data($response);
        /** @var list<array<string, mixed>> $events */
        $events = \is_array($data['events'] ?? null) ? $data['events'] : [];

        $mapped = [];
        foreach ($events as $event) {
            $mapped[] = $this->toTrackingEvent($event, $stampTrackingNumber);
        }

        return new TrackingEventCollection(...$mapped);
    }

    /**
     * A single event, as sent inside a tracking response OR a webhook payload.
     *
     * @param array<string, mixed> $event
     */
    public function toTrackingEvent(array $event, string $stampTrackingNumber): TrackingEvent
    {
        return new TrackingEvent(
            providerKey: self::PROVIDER_KEY,
            trackingNumber: $stampTrackingNumber,
            status: $this->mapStatus((string) ($event['status'] ?? 'unknown')),
            occurredAt: new \DateTimeImmutable((string) ($event['datetime'] ?? $event['date'] ?? 'now')),
            description: isset($event['description']) ? (string) $event['description'] : null,
            location: isset($event['location']) ? (string) $event['location'] : null,
        );
    }

    public function mapStatus(string $tshipStatus): TrackingStatus
    {
        return match (strtolower(str_replace([' ', '_'], '-', $tshipStatus))) {
            'draft', 'pending', 'awaiting-confirmation' => TrackingStatus::PENDING,
            'confirmed', 'created', 'booked' => TrackingStatus::CREATED,
            'picked-up', 'in-transit', 'transit' => TrackingStatus::IN_TRANSIT,
            'out-for-delivery' => TrackingStatus::OUT_FOR_DELIVERY,
            'delivered', 'completed' => TrackingStatus::DELIVERED,
            'cancelled', 'canceled' => TrackingStatus::CANCELLED,
            'returned', 'return' => TrackingStatus::RETURNED,
            'failed', 'exception', 'delivery-failed' => TrackingStatus::EXCEPTION,
            default => TrackingStatus::UNKNOWN,
        };
    }

    /**
     * Stable content hash for id reuse (address / parcel dedup).
     */
    public function addressHash(Address $address): string
    {
        return hash('sha256', implode('|', [
            strtoupper($address->countryCode),
            strtoupper($address->city),
            strtoupper((string) $address->postalCode),
            strtoupper($address->line1),
            strtoupper((string) $address->state),
        ]));
    }

    public function parcelHash(RateRequest $request, ?string $packagingId): string
    {
        return hash('sha256', implode('|', [
            $request->weight->grams,
            $request->dimensions->lengthMm,
            $request->dimensions->widthMm,
            $request->dimensions->heightMm,
            $request->currency->value,
            (string) $packagingId,
        ]));
    }

    // --- helpers -------------------------------------------------------------

    /**
     * @param array<string, mixed> $rate
     */
    private function rateCurrency(array $rate, Currency $fallback): Currency
    {
        if (isset($rate['currency']) && \is_string($rate['currency'])) {
            $currency = Currency::tryFrom($rate['currency']);
            if (null !== $currency) {
                return $currency;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function data(array $response): array
    {
        $data = $response['data'] ?? null;
        if (!\is_array($data)) {
            throw new ProviderException('TShip response missing a data object.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Rates come back as data:[...] or data:{rates:[...]} across TShip endpoints.
     *
     * @param array<string, mixed> $response
     *
     * @return list<array<string, mixed>>
     */
    private function dataList(array $response): array
    {
        $data = $response['data'] ?? [];
        if (isset($data['rates']) && \is_array($data['rates'])) {
            $data = $data['rates'];
        }

        if (!\is_array($data)) {
            throw new ProviderException('TShip rates response has no rate list.');
        }

        /** @var list<array<string, mixed>> $list */
        $list = array_values(array_filter($data, '\is_array'));

        return $list;
    }
}
