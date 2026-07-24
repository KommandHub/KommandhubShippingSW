<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Shipment;

use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use Kommandhub\ShippingSW\Provider\ProviderContextFactory;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Builds a canonical ShipmentRequest from a Shopware order. This is the one
 * order-to-canonical boundary for shipment creation; the admin action and any
 * auto-create-on-payment subscriber both go through it, so they stay identical.
 *
 * ponytail: origin is a warehouse placeholder and parcel dimensions are nominal
 * — both are merchant configuration to wire before go-live (marked TODO).
 */
final class OrderShipmentRequestFactory
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly ProviderContextFactory $contextFactory,
    ) {
    }

    public function fromOrderId(string $orderId, string $serviceCode, Context $context): ?ShipmentRequest
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('deliveries.shippingOrderAddress.country');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();
        if (null === $order) {
            return null;
        }

        $providerKey = $this->contextFactory->activeProviderKey($order->getSalesChannelId());
        $delivery = $order->getDeliveries()?->first();
        if (null === $providerKey || !$delivery instanceof OrderDeliveryEntity) {
            return null;
        }

        $address = $delivery->getShippingOrderAddress();
        if (null === $address || null === $address->getCountry()) {
            return null;
        }

        $destination = new Address(
            countryCode: (string) $address->getCountry()->getIso(),
            city: (string) $address->getCity(),
            line1: (string) $address->getStreet(),
            postalCode: $address->getZipcode(),
            name: trim($address->getFirstName() . ' ' . $address->getLastName()),
            phone: $address->getPhoneNumber(),
        );

        return new ShipmentRequest(
            providerKey: $providerKey,
            serviceCode: $serviceCode,
            origin: new Address(countryCode: $destination->countryCode, city: 'Warehouse', line1: 'Warehouse'), // TODO: warehouse origin
            destination: $destination,
            weight: Weight::fromGrams($this->orderWeightGrams($order)),
            dimensions: Dimensions::fromCentimeters(30, 20, 10), // TODO: real parcel packing
            reference: (string) $order->getOrderNumber(),
            idempotencyKey: 'order-' . $orderId,
        );
    }

    private function orderWeightGrams(OrderEntity $order): int
    {
        $kg = 0.0;
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $weight = $lineItem->getWeight();
            if (null !== $weight) {
                $kg += $weight * $lineItem->getQuantity();
            }
        }

        return (int) round($kg * 1000);
    }
}
