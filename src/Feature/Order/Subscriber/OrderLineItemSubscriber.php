<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Order\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderLineItemSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $orderRepository
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'order_line_item.loaded' => 'onOrderLineItemsLoaded',
        ];
    }

    public function onOrderLineItemsLoaded(EntityLoadedEvent $event): void
    {
        $context = $event->getContext();
        
        $this->logger->info('SHIPPERHQ: OrderLineItemSubscriber triggered', [
            'entity_count' => count($event->getEntities())
        ]);

        foreach ($event->getEntities() as $lineItem) {
            if (!$lineItem instanceof OrderLineItemEntity) {
                $this->logger->warning('SHIPPERHQ: Entity is not an OrderLineItemEntity', [
                    'entity_type' => get_class($lineItem)
                ]);
                continue;
            }

            $this->logger->info('SHIPPERHQ: Processing line item', [
                'line_item_id' => $lineItem->getId(),
                'order_id' => $lineItem->getOrderId(),
                'type' => $lineItem->getType()
            ]);

            // Create criteria with order ID filter
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $lineItem->getOrderId()));
            $criteria->addAssociation('deliveries');

            // Get the order with its deliveries
            $order = $this->orderRepository->search($criteria, $context)->first();

            if (!$order instanceof OrderEntity) {
                $this->logger->warning('SHIPPERHQ: Order not found for line item', [
                    'line_item_id' => $lineItem->getId(),
                    'order_id' => $lineItem->getOrderId()
                ]);
                continue;
            }

            $this->logger->info('SHIPPERHQ: Found order', [
                'order_id' => $order->getId(),
                'has_deliveries' => $order->getDeliveries() !== null,
                'delivery_count' => $order->getDeliveries() ? $order->getDeliveries()->count() : 0
            ]);

            // Try to get delivery date from order's deliveries
            $deliveryDate = null;
            foreach ($order->getDeliveries() ?? [] as $delivery) {
                $deliveryCustomFields = $delivery->getCustomFields();
                if ($deliveryCustomFields && isset($deliveryCustomFields['shipperhq_delivery_date'])) {
                    $deliveryDate = $deliveryCustomFields['shipperhq_delivery_date'];
                    break;
                }
            }

            if ($deliveryDate) {
                $this->logger->info('SHIPPERHQ: Found delivery date', [
                    'line_item_id' => $lineItem->getId(),
                    'delivery_date' => $deliveryDate
                ]);

                // Get existing additional information or create new array
                $additionalInformation = $lineItem->getAdditionalInformation() ?? [];
                
                // Add delivery date to additional information
                $additionalInformation['shipperhq_delivery_date'] = $deliveryDate;
                
                // Set the updated additional information
                $lineItem->setAdditionalInformation($additionalInformation);

                $this->logger->info('SHIPPERHQ: Set additional information', [
                    'line_item_id' => $lineItem->getId(),
                    'additional_info' => $additionalInformation
                ]);
            } else {
                $this->logger->warning('SHIPPERHQ: No delivery date found in order deliveries', [
                    'line_item_id' => $lineItem->getId(),
                    'order_id' => $order->getId()
                ]);
            }
        }
    }
} 