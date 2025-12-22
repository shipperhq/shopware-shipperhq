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

use SHQ\RateProvider\Feature\Checkout\Service\RateCacheKeyGenerator;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheKeyEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShippingMethodRouteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateCacheKeyGenerator $rateCacheKeyGenerator,
        private readonly CartService $cartService,
    ) {
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ShippingMethodRouteCacheKeyEvent::class => 'shippingMethodRouteCacheKey',
        ];
    }

    public function shippingMethodRouteCacheKey(ShippingMethodRouteCacheKeyEvent $event): void
    {
        // getSalesChannelContext() was added in Shopware 6.7.1.0
        // For older versions, use getContext() which exists in the base StoreApiRouteCacheKeyEvent class
        if (method_exists($event, 'getSalesChannelContext')) {
            $context = $event->getSalesChannelContext();
        } elseif (method_exists($event, 'getContext')) {
            $context = $event->getContext();
        } else {
            // Fallback: disable caching if we can't get the context
            if (method_exists($event, 'disableCaching')) {
                $event->disableCaching();
            }
            return;
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        if (!$cart) {
            return;
        }

        // Generate SHQ-specific cache key
        $shqCacheKey = $this->rateCacheKeyGenerator->generateKey($cart, $context);
        
        // Add the SHQ cache key to the parts array
        // This allows proper caching while including SHQ-specific cart data in the key
        $parts = $event->getParts();
        $parts['shq_cache_key'] = $shqCacheKey;
        $event->setParts($parts);
    }
}
