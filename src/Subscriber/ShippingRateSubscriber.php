<?php declare(strict_types=1);

namespace SHQ\RateProvider\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SHQ\RateProvider\Service\ShippingRateCalculator;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Shipping\Cart\Event\ShippingMethodPriceCalculationEvent;

class ShippingRateSubscriber implements EventSubscriberInterface
{
    private ShippingRateCalculator $calculator;
    private LoggerInterface $logger;

    public function __construct(
        ShippingRateCalculator $calculator,
        LoggerInterface $logger
    ) {
        $this->calculator = $calculator;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ShippingMethodPriceCalculationEvent::class => 'onShippingMethodPriceCalculation',
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced'
        ];
    }

    public function onShippingMethodPriceCalculation(ShippingMethodPriceCalculationEvent $event): void
    {
        $shippingMethod = $event->getShippingMethod();
        
        // Only handle ShipperHQ methods
        if (!$this->isShipperHQMethod($shippingMethod)) {
            return;
        }

        $context = [
            'cart' => $event->getCart(),
            'salesChannelContext' => $event->getSalesChannelContext()
        ];

        $price = $this->calculator->calculateRate(
            $shippingMethod->getId(),
            $context
        );

        if ($price === null) {
            $event->addError(new ShippingMethodBlockedError($shippingMethod->getId()));
            return;
        }

        $event->setCalculatedPrice($price);
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        // Handle order placement notification to ShipperHQ if needed
    }

    private function isShipperHQMethod(ShippingMethodEntity $method): bool
    {
        return str_starts_with($method->getTechnicalName(), 'shipperhq_');
    }
} 