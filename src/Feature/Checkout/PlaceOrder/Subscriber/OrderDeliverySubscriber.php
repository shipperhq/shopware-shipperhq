<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Checkout\PlaceOrder\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDeliverySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $orderDeliveryRepository,
        private readonly LoggerInterface  $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        foreach ($event->getOrder()->getDeliveries() as $delivery) {
            $shippingMethod = $delivery->getShippingMethod();
            if (!$shippingMethod) {
                continue;
            }

            $shippingMethodCustomFields = $shippingMethod->getCustomFields() ?? [];
            $deliveryDate = $shippingMethodCustomFields['shipperhq_delivery_date'] ?? null;
            $dispatchDate = $shippingMethodCustomFields['shipperhq_dispatch_date'] ?? null;

            if (!$deliveryDate) {
                continue;
            }

            $newCustom = ($delivery->getCustomFields() ?? []) + [
                'shipperhq_delivery_date' => $deliveryDate,
                'shipperhq_dispatch_date' => $dispatchDate,
            ];

            $this->orderDeliveryRepository->update([
                [
                    'id'           => $delivery->getId(),
                    'customFields' => $newCustom,
                ],
            ], $event->getContext());
        }
    }
}