<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\BobGo;

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
 * The ONLY place Bob Go's payload shape is known. Bob Go names things quite
 * differently from Terminal — rates carry `service_level_code`/`rate`,
 * shipments carry `tracking_reference`, tracking uses `checkpoints` with
 * `timestamp` — yet every field is normalised to the identical canonical model.
 * That the core can't tell the two providers apart is the whole point.
 */
final class BobGoMapper
{
    public const PROVIDER_KEY = 'bobgo';

    /**
     * @return array<string, mixed>
     */
    public function ratesRequestPayload(RateRequest $request): array
    {
        return [
            'collection_address' => $this->address($request->origin),
            'delivery_address' => $this->address($request->destination),
            'parcels' => [[
                'submitted_length_cm' => $request->dimensions->lengthMm / 10,
                'submitted_width_cm' => $request->dimensions->widthMm / 10,
                'submitted_height_cm' => $request->dimensions->heightMm / 10,
                'submitted_weight_kg' => $request->weight->kilograms(),
            ]],
            'declared_value' => $request->declaredValue?->major(),
            'currency' => $request->currency->value,
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    public function toRateQuotes(array $response, Currency $currency): RateQuoteCollection
    {
        /** @var list<array<string, mixed>> $rates */
        $rates = \is_array($response['rates'] ?? null) ? array_values(array_filter($response['rates'], '\is_array')) : [];

        $quotes = [];
        foreach ($rates as $rate) {
            $quotes[] = new RateQuote(
                providerKey: self::PROVIDER_KEY,
                serviceCode: (string) $rate['service_level_code'],
                serviceName: (string) ($rate['service_level_name'] ?? $rate['service_level_code']),
                amount: Money::fromMajor((string) $rate['rate'], $this->currency($rate, $currency)),
                estimatedDaysMin: isset($rate['min_delivery_days']) ? (int) $rate['min_delivery_days'] : null,
                estimatedDaysMax: isset($rate['max_delivery_days']) ? (int) $rate['max_delivery_days'] : null,
                carrierName: isset($rate['provider']) ? (string) $rate['provider'] : null,
            );
        }

        return new RateQuoteCollection(...$quotes);
    }

    /**
     * @return array<string, mixed>
     */
    public function shipmentRequestPayload(ShipmentRequest $request): array
    {
        return [
            'service_level_code' => $request->serviceCode,
            'customer_reference' => $request->reference,
            'idempotency_key' => $request->idempotencyKey,
            'collection_address' => $this->address($request->origin),
            'delivery_address' => $this->address($request->destination),
            'parcels' => [[
                'submitted_length_cm' => $request->dimensions->lengthMm / 10,
                'submitted_width_cm' => $request->dimensions->widthMm / 10,
                'submitted_height_cm' => $request->dimensions->heightMm / 10,
                'submitted_weight_kg' => $request->weight->kilograms(),
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    public function toShipment(array $response, string $serviceCode): Shipment
    {
        $shipment = $this->node($response, 'shipment');

        return new Shipment(
            providerKey: self::PROVIDER_KEY,
            providerShipmentId: (string) ($shipment['id'] ?? throw new ProviderException('Bob Go shipment response missing id.')),
            serviceCode: $serviceCode,
            status: $this->mapStatus((string) ($shipment['status'] ?? 'pending')),
            trackingNumber: isset($shipment['tracking_reference']) ? (string) $shipment['tracking_reference'] : null,
            trackingUrl: isset($shipment['tracking_url']) ? (string) $shipment['tracking_url'] : null,
            createdAt: new \DateTimeImmutable(),
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    public function toLabel(array $response): Label
    {
        $shipment = $this->node($response, 'shipment');
        $url = $shipment['label_url'] ?? null;

        if (null === $url) {
            throw new ProviderException('Bob Go label response missing label_url.');
        }

        return new Label(LabelFormat::PDF, url: (string) $url);
    }

    /**
     * @param array<string, mixed> $response
     */
    public function toTrackingEvents(array $response, string $trackingNumber): TrackingEventCollection
    {
        $tracking = $this->node($response, 'tracking');
        /** @var list<array<string, mixed>> $checkpoints */
        $checkpoints = \is_array($tracking['checkpoints'] ?? null) ? $tracking['checkpoints'] : [];

        $events = [];
        foreach ($checkpoints as $checkpoint) {
            $events[] = $this->toTrackingEvent($checkpoint, $trackingNumber);
        }

        return new TrackingEventCollection(...$events);
    }

    /**
     * @param array<string, mixed> $checkpoint a checkpoint or a webhook data node
     */
    public function toTrackingEvent(array $checkpoint, string $trackingNumber): TrackingEvent
    {
        return new TrackingEvent(
            providerKey: self::PROVIDER_KEY,
            trackingNumber: $trackingNumber,
            status: $this->mapStatus((string) ($checkpoint['status'] ?? 'unknown')),
            occurredAt: new \DateTimeImmutable((string) ($checkpoint['timestamp'] ?? $checkpoint['time'] ?? 'now')),
            description: isset($checkpoint['message']) ? (string) $checkpoint['message'] : null,
            location: isset($checkpoint['location']) ? (string) $checkpoint['location'] : null,
        );
    }

    public function mapStatus(string $bobGoStatus): TrackingStatus
    {
        return match (strtolower(str_replace([' ', '_'], '-', $bobGoStatus))) {
            'pending', 'submitted' => TrackingStatus::PENDING,
            'confirmed', 'collected', 'created' => TrackingStatus::CREATED,
            'in-transit', 'collected-in-transit' => TrackingStatus::IN_TRANSIT,
            'out-for-delivery' => TrackingStatus::OUT_FOR_DELIVERY,
            'delivered' => TrackingStatus::DELIVERED,
            'cancelled', 'canceled' => TrackingStatus::CANCELLED,
            'returned', 'return-to-sender' => TrackingStatus::RETURNED,
            'failed-delivery', 'exception' => TrackingStatus::EXCEPTION,
            default => TrackingStatus::UNKNOWN,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function address(Address $address): array
    {
        return array_filter([
            'country' => $address->countryCode,
            'city' => $address->city,
            'street_address' => $address->line1,
            'local_area' => $address->line2,
            'zone' => $address->state,
            'code' => $address->postalCode,
            'company' => $address->company,
            'contact_name' => $address->name,
            'contact_number' => $address->phone,
            'contact_email' => $address->email,
        ], static fn ($v): bool => null !== $v);
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function currency(array $rate, Currency $fallback): Currency
    {
        if (isset($rate['currency']) && \is_string($rate['currency'])) {
            return Currency::tryFrom($rate['currency']) ?? $fallback;
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function node(array $response, string $key): array
    {
        $node = $response[$key] ?? null;
        if (!\is_array($node)) {
            throw new ProviderException(sprintf('Bob Go response missing "%s" object.', $key));
        }

        /** @var array<string, mixed> $node */
        return $node;
    }
}
