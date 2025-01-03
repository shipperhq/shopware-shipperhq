<?php declare(strict_types=1);

namespace SHQ\RateProvider\Subscriber;

use Psr\Log\LoggerInterface;
use SHQ\RateProvider\Service\ShippingRateCalculator;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Cart\Event\ShippingMethodPriceCalculationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\Struct\ArrayStruct;
use SHQ\RateProvider\Helper\Debug;

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
            'Shopware\Core\Checkout\Cart\Event\ShippingMethodPriceCalculationEvent' => 'onShippingMethodCalculation'
        ];
    }

    public function onShippingMethodCalculation(ShippingMethodPriceCalculationEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $cart = $context->getCart();
        
        $this->logger->debug('ShippingRateSubscriber triggered', [
            'cart_total' => $cart->getPrice()->getTotalPrice(),
            'items_count' => $cart->getLineItems()->count(),
            'event_class' => get_class($event)
        ]);

        try {
            $rates = $this->calculator->calculate($context);
            
            Debug::dump($rates, 'Calculated Rates');
            
            foreach ($rates as $rate) {
                $deliveryTime = new ArrayStruct([
                    'min' => $rate['deliveryTime']['earliest'],
                    'max' => $rate['deliveryTime']['latest'],
                    'unit' => $rate['deliveryTime']['unit'],
                    'name' => sprintf('%d-%d business days', 
                        $rate['deliveryTime']['earliest'],
                        $rate['deliveryTime']['latest']
                    )
                ]);

                $this->logger->debug('Adding shipping method', [
                    'name' => $rate['name'],
                    'price' => $rate['price']
                ]);

                $event->addShippingMethod(
                    $rate['name'],
                    $rate['price'],
                    $deliveryTime
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate shipping rates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $this->logger->debug('Detailed debug info', [
            'cart' => [
                'total' => $cart->getPrice()->getTotalPrice(),
                'items' => $cart->getLineItems()->count(),
            ],
            'context' => [
                'salesChannelId' => $context->getSalesChannelId(),
                'currencyId' => $context->getCurrencyId(),
            ],
            'trace' => (new \Exception())->getTraceAsString()
        ]);
    }
}
