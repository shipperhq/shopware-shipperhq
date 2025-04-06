<?php declare(strict_types=1);

namespace SHQ\RateProvider\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Psr\Log\LoggerInterface;

class DeliveryProcessorDecorator implements CartProcessorInterface, CartDataCollectorInterface
{
    public function __construct(
        private readonly DeliveryProcessor $decorated,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function buildKey(string $shippingMethodId): string
    {
        return DeliveryProcessor::buildKey($shippingMethodId);
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $this->logger->debug('SHIPPERHQ: Starting shipping method collection');

        // Get all shipping methods that are either ShipperHQ methods or the current shipping method
        $ids = [];
        
        // Add current shipping method
        $ids[] = $context->getShippingMethod()->getId();
        
        // Add any existing delivery methods
        foreach ($original->getDeliveries() as $delivery) {
            $ids[] = $delivery->getShippingMethod()->getId();
        }

        // Get all ShipperHQ methods
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('customFields.shipperhq_method', true));
        $criteria->addAssociation('prices');
        $criteria->addAssociation('deliveryTime');
        $criteria->addAssociation('tax');
        $criteria->setTitle('cart::shipping-methods');

        $shipperMethods = $this->shippingMethodRepository->search($criteria, $context->getContext());

        // Add ShipperHQ method IDs
        foreach ($shipperMethods as $method) {
            $ids[] = $method->getId();
        }

        // Remove duplicates
        $ids = array_unique($ids);

        $this->logger->debug('SHIPPERHQ: Found shipping methods', [
            'method_ids' => $ids,
            'shipper_methods_count' => $shipperMethods->count()
        ]);

        // Get all shipping methods
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria($ids);
        $criteria->addAssociation('prices');
        $criteria->addAssociation('deliveryTime');
        $criteria->addAssociation('tax');
        $criteria->setTitle('cart::shipping-methods');

        $shippingMethods = $this->shippingMethodRepository->search($criteria, $context->getContext());

        // Add all shipping methods to the data collection
        foreach ($shippingMethods as $method) {
            $key = self::buildKey($method->getId());
            $data->set($key, $method);
            $this->logger->debug('SHIPPERHQ: Added method to data collection', [
                'method_id' => $method->getId(),
                'method_name' => $method->getName(),
                'is_shipperhq' => $method->getCustomFields()['shipperhq_method'] ?? false
            ]);
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $this->logger->debug('SHIPPERHQ: Starting delivery processing');

        // Let the core handle the delivery processing
        $this->decorated->process($data, $original, $toCalculate, $context, $behavior);

        // Log the final deliveries
        foreach ($toCalculate->getDeliveries() as $delivery) {
            $this->logger->debug('SHIPPERHQ: Final delivery', [
                'method_id' => $delivery->getShippingMethod()->getId(),
                'method_name' => $delivery->getShippingMethod()->getName(),
                'shipping_costs' => $delivery->getShippingCosts() ? $delivery->getShippingCosts()->getTotalPrice() : 0
            ]);
        }
    }
} 