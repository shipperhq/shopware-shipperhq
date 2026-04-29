<?php declare(strict_types=1);

/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package SHQ\RateProvider
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace SHQ\RateProvider\Feature\Checkout\Rating\Subscriber;

use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets cart behavior permissions on cart creation to control Shopware's
 * built-in recalculation (e.g. allowing free shipping methods).
 */
class CartEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CartCreatedEvent::class => 'onCartCreated',
        ];
    }

    public function onCartCreated(CartCreatedEvent $event): void
    {
        $permissions = [
            'skipPromotion' => true,
            'skipDeliveryRecalculation' => true,
            'skipProductRecalculation' => true,
            'skipDiscountRecalculation' => true,
            'skipDeliveryPriceRecalculation' => true // SHQ23-6063 This allows free shipping methods
        ];

        $behavior = new CartBehavior($permissions);
        $event->getCart()->setBehavior($behavior);
    }
}
