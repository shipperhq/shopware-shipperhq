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
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemRemovedEvent;
use SHQ\RateProvider\Feature\Checkout\Service\ShippingRateCache;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class CartEventSubscriber
 * 
 * This subscriber is responsible for clearing the ShipperHQ shipping rate cache when cart-related events occur.
 * It listens for cart changes (items added, removed, quantities changed) and customer address changes,
 * then clears the cached shipping rates to ensure they are recalculated with the updated information.
 * 
 * Without this subscriber, shipping rates might become stale and not reflect the current state of the cart
 * or customer address, which could lead to incorrect shipping costs being displayed to customers.
 * 
 * @package SHQ\RateProvider\Subscriber
 */
class CartEventSubscriber implements EventSubscriberInterface
{
    private ShippingRateCache $rateCache;

    public function __construct(ShippingRateCache $rateCache)
    {
        $this->rateCache = $rateCache;
    }

    public static function getSubscribedEvents(): array
    {
        // Address change events are already handled by shopware
        return [
            // Cart change events
            CartChangedEvent::class => 'onCartChanged',
            BeforeLineItemAddedEvent::class => 'onLineItemAdded',
            LineItemRemovedEvent::class => 'onLineItemRemoved',
            BeforeLineItemQuantityChangedEvent::class => 'onLineItemQuantityChanged',  
            CartCreatedEvent::class => 'onCartCreated',
        ];
    }

    /**
     * Handle cart changed event
     */
    public function onCartChanged(): void
    {
        $this->rateCache->clearCache();
    }

    /**
     * Handle line item added event
     */
    public function onLineItemAdded(): void
    {
        $this->rateCache->clearCache();
    }

    /**
     * Handle line item removed event
     */
    public function onLineItemRemoved(): void
    {
        $this->rateCache->clearCache();
    }

    /**
     * Handle line item quantity changed event
     */
    public function onLineItemQuantityChanged(): void
    {
        $this->rateCache->clearCache();
    }

    /**
     * Handle customer address changed event
     */
    public function onCustomerAddressChanged(): void
    {
        $this->rateCache->clearCache();
    }

    public function onCartCreated(CartCreatedEvent $event): void
    {
        $permissions = [
            'skipPromotion' => true,
            'skipDeliveryRecalculation' => true,
            'skipProductRecalculation' => true,
            'skipDiscountRecalculation' => true,
        ];

        $behavior = new CartBehavior($permissions);
        $event->getCart()->setBehavior($behavior);
    }
}
