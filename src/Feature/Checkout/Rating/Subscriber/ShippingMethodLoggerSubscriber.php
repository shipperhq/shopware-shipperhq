<?php declare(strict_types=1);
/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package ShipperHQ_Calendar
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace SHQ\RateProvider\Feature\Checkout\Rating\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShippingMethodLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartChangedEvent::class => 'onCartChanged'
        ];
    }

    public function onCartChanged(CartChangedEvent $event): void
    {
        $cart = $event->getCart();
        $deliveries = $cart->getDeliveries();

        foreach ($deliveries as $delivery) {
            $this->logger->info('Processing delivery', [
                'shipping_method_id' => $delivery->getShippingMethod()->getId(),
                'shipping_method_name' => $delivery->getShippingMethod()->getName(),
                'shipping_costs' => $delivery->getShippingCosts()->getUnitPrice()
            ]);
        }
    }
}
