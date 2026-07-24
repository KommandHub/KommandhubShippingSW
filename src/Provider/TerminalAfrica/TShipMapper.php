<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\TerminalAfrica;

use Kommandhub\ShippingSW\Model\Pickup\Pickup;
use Kommandhub\ShippingSW\Model\Pickup\PickupRequest;
use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\Shipment\Label;
use Kommandhub\ShippingSW\Model\Shipment\LabelFormat;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEvent;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEventCollection;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use Kommandhub\ShippingSW\Provider\Exception\ProviderException;

/**
 * The ONLY place TShip's payload shape is known. Every field name, unit choice,
 * and status vocabulary of Terminal Africa is confined here, so the adapter and
 * the whole core speak canonical model. If Terminal changes a field, this file
 * changes and nothing else does.
 *
 * NB: field names below reflect the documented TShip shape; where the live API
 * differs, correct it here (and only here). Amounts are treated as major units
 * in the quote currency and normalised to Money minor units on the way in.
 */
final class TShipMapper
{
    public const PROVIDER_KEY = 'terminal_africa';

    // --- Rates ---------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function ratesRequestPayload(RateRequest $request): array
    {
        return [
            'pickup_address' => $this->address($request->origin),
            'delivery_address' => $this->address($request->destination),
            'parcel' => [
                'weight' => $request->weight->kilograms(),
                'length' => $request->dimensions->lengthMm / 10,
                'width' => $request->dimensions->widthMm / 10,
                'height' => $request->dimensions->heightMm / 10,
                'value' => $request->declaredValue?->major(),
            ],
            'currency' => $request->currency->value,
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
            );
        }

        return new RateQuoteCollection(...$quotes);
    }

    // --- Shipment ------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function shipmentRequestPayload(ShipmentRequest $request): array
    {
        return [
            'rate_id' => $request->serviceCode,
            'reference' => $request->reference,
            // Terminal has no idempotency header; we send our key as metadata so
            // duplicates are at least detectable server-side. Real dedup is the
            // gateway's unique idempotency_key.
            'metadata' => ['idempotency_key' => $request->idempotencyKey],
            'pickup_address' => $this->address($request->origin),
            'delivery_address' => $this->address($request->destination),
            'parcel' => [
                'weight' => $request->weight->kilograms(),
                'length' => $request->dimensions->lengthMm / 10,
                'width' => $request->dimensions->widthMm / 10,
                'height' => $request->dimensions->heightMm / 10,
                'value' => $request->declaredValue?->major(),
            ],
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
            trackingNumber: isset($data['tracking_number']) ? (string) $data['tracking_number'] : null,
            trackingUrl: isset($data['tracking_url']) ? (string) $data['tracking_url'] : null,
            createdAt: new \DateTimeImmutable(),
        );
    }

    // --- Label ---------------------------------------------------------------

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

    // --- Pickup --------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function pickupRequestPayload(PickupRequest $request): array
    {
        return [
            'shipment_ids' => $request->providerShipmentIds,
            'pickup_date' => $request->readyAt->format(\DateTimeInterface::ATOM),
            'pickup_address' => $this->address($request->location),
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    public function toPickup(array $response): Pickup
    {
        $data = $this->data($response);

        return new Pickup(
            providerKey: self::PROVIDER_KEY,
            providerPickupId: (string) ($data['pickup_id'] ?? $data['id'] ?? throw new ProviderException('TShip pickup response missing pickup_id.')),
            scheduledAt: new \DateTimeImmutable((string) ($data['pickup_date'] ?? 'now')),
            status: (string) ($data['status'] ?? 'scheduled'),
        );
    }

    // --- Tracking ------------------------------------------------------------

    /**
     * @param array<string, mixed> $response
     */
    public function toTrackingEvents(array $response, string $trackingNumber): TrackingEventCollection
    {
        $data = $this->data($response);
        /** @var list<array<string, mixed>> $events */
        $events = \is_array($data['events'] ?? null) ? $data['events'] : [];

        $mapped = [];
        foreach ($events as $event) {
            $mapped[] = $this->toTrackingEvent($event, $trackingNumber);
        }

        return new TrackingEventCollection(...$mapped);
    }

    /**
     * A single event, as sent inside a tracking response OR a webhook payload.
     *
     * @param array<string, mixed> $event
     */
    public function toTrackingEvent(array $event, string $trackingNumber): TrackingEvent
    {
        return new TrackingEvent(
            providerKey: self::PROVIDER_KEY,
            trackingNumber: $trackingNumber,
            status: $this->mapStatus((string) ($event['status'] ?? 'unknown')),
            occurredAt: new \DateTimeImmutable((string) ($event['datetime'] ?? $event['date'] ?? 'now')),
            description: isset($event['description']) ? (string) $event['description'] : null,
            location: isset($event['location']) ? (string) $event['location'] : null,
        );
    }

    /**
     * Translate a TShip status token to the canonical lifecycle. Unknown tokens
     * map to UNKNOWN rather than throwing — a new provider status must never
     * break the delivery pipeline.
     */
    public function mapStatus(string $tshipStatus): TrackingStatus
    {
        return match (strtolower(str_replace([' ', '_'], '-', $tshipStatus))) {
            'pending', 'awaiting-confirmation' => TrackingStatus::PENDING,
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

    // --- helpers -------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function address(Address $address): array
    {
        return array_filter([
            'country' => $address->countryCode,
            'city' => $address->city,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'state' => $address->state,
            'zip' => $address->postalCode,
            'name' => $address->name,
            'phone' => $address->phone,
            'email' => $address->email,
        ], static fn ($v): bool => null !== $v);
    }

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
     * Rates come back either as data:[...] or data:{rates:[...]} across TShip
     * endpoints; accept both.
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
