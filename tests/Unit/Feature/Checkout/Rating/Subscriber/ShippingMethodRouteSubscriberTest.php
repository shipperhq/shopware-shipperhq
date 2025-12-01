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

namespace SHQ\RateProvider\Tests\Unit\Feature\Checkout\Rating\Subscriber;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SHQ\RateProvider\Feature\Checkout\Rating\Subscriber\ShippingMethodRouteSubscriber;
use SHQ\RateProvider\Feature\Checkout\Service\RateCacheKeyGenerator;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheKeyEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ShippingMethodRouteSubscriberTest extends TestCase
{
    private ShippingMethodRouteSubscriber $subscriber;
    private RateCacheKeyGenerator|MockObject $rateCacheKeyGenerator;
    private CartService|MockObject $cartService;

    protected function setUp(): void
    {
        $this->rateCacheKeyGenerator = $this->createMock(RateCacheKeyGenerator::class);
        $this->cartService = $this->createMock(CartService::class);
        
        $this->subscriber = new ShippingMethodRouteSubscriber(
            $this->rateCacheKeyGenerator,
            $this->cartService
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ShippingMethodRouteSubscriber::getSubscribedEvents();
        
        $this->assertArrayHasKey(ShippingMethodRouteCacheKeyEvent::class, $events);
        $this->assertEquals('shippingMethodRouteCacheKey', $events[ShippingMethodRouteCacheKeyEvent::class]);
    }

    public function testShippingMethodRouteCacheKeyWithValidCart(): void
    {
        // Arrange
        $context = $this->createMock(SalesChannelContext::class);
        $cart = $this->createMock(Cart::class);
        $event = $this->createMock(ShippingMethodRouteCacheKeyEvent::class);
        
        $expectedCacheKey = 'shipperhq_shipping_rates_abc123';
        $initialParts = ['existing_key' => 'existing_value'];
        $expectedParts = [
            'existing_key' => 'existing_value',
            'shq_cache_key' => $expectedCacheKey
        ];

        $event->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn($context);

        $event->expects($this->once())
            ->method('getParts')
            ->willReturn($initialParts);

        $event->expects($this->once())
            ->method('setParts')
            ->with($expectedParts);

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($context->getToken(), $context)
            ->willReturn($cart);

        $this->rateCacheKeyGenerator->expects($this->once())
            ->method('generateKey')
            ->with($cart, $context)
            ->willReturn($expectedCacheKey);

        // Act
        $this->subscriber->shippingMethodRouteCacheKey($event);
    }

    public function testShippingMethodRouteCacheKeyWithNullCart(): void
    {
        // Arrange
        $context = $this->createMock(SalesChannelContext::class);
        $event = $this->createMock(ShippingMethodRouteCacheKeyEvent::class);
        
        $initialParts = ['existing_key' => 'existing_value'];

        $event->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn($context);

        $event->expects($this->never())
            ->method('getParts');

        $event->expects($this->never())
            ->method('setParts');

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($context->getToken(), $context)
            ->willReturn(null);

        $this->rateCacheKeyGenerator->expects($this->never())
            ->method('generateKey');

        // Act
        $this->subscriber->shippingMethodRouteCacheKey($event);
    }

    public function testShippingMethodRouteCacheKeyWithEmptyParts(): void
    {
        // Arrange
        $context = $this->createMock(SalesChannelContext::class);
        $cart = $this->createMock(Cart::class);
        $event = $this->createMock(ShippingMethodRouteCacheKeyEvent::class);
        
        $expectedCacheKey = 'shipperhq_shipping_rates_xyz789';
        $initialParts = [];
        $expectedParts = [
            'shq_cache_key' => $expectedCacheKey
        ];

        $event->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn($context);

        $event->expects($this->once())
            ->method('getParts')
            ->willReturn($initialParts);

        $event->expects($this->once())
            ->method('setParts')
            ->with($expectedParts);

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($context->getToken(), $context)
            ->willReturn($cart);

        $this->rateCacheKeyGenerator->expects($this->once())
            ->method('generateKey')
            ->with($cart, $context)
            ->willReturn($expectedCacheKey);

        // Act
        $this->subscriber->shippingMethodRouteCacheKey($event);
    }

    public function testShippingMethodRouteCacheKeyPreservesExistingParts(): void
    {
        // Arrange
        $context = $this->createMock(SalesChannelContext::class);
        $cart = $this->createMock(Cart::class);
        $event = $this->createMock(ShippingMethodRouteCacheKeyEvent::class);
        
        $expectedCacheKey = 'shipperhq_shipping_rates_def456';
        $initialParts = [
            'route_key' => 'shipping_method_route',
            'context_key' => 'sales_channel_context',
            'criteria_key' => 'search_criteria'
        ];
        $expectedParts = [
            'route_key' => 'shipping_method_route',
            'context_key' => 'sales_channel_context',
            'criteria_key' => 'search_criteria',
            'shq_cache_key' => $expectedCacheKey
        ];

        $event->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn($context);

        $event->expects($this->once())
            ->method('getParts')
            ->willReturn($initialParts);

        $event->expects($this->once())
            ->method('setParts')
            ->with($expectedParts);

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($context->getToken(), $context)
            ->willReturn($cart);

        $this->rateCacheKeyGenerator->expects($this->once())
            ->method('generateKey')
            ->with($cart, $context)
            ->willReturn($expectedCacheKey);

        // Act
        $this->subscriber->shippingMethodRouteCacheKey($event);
    }

    public function testShippingMethodRouteCacheKeyOverwritesExistingShqKey(): void
    {
        // Arrange
        $context = $this->createMock(SalesChannelContext::class);
        $cart = $this->createMock(Cart::class);
        $event = $this->createMock(ShippingMethodRouteCacheKeyEvent::class);
        
        $expectedCacheKey = 'shipperhq_shipping_rates_new789';
        $initialParts = [
            'route_key' => 'shipping_method_route',
            'shq_cache_key' => 'old_shq_key_123',
            'context_key' => 'sales_channel_context'
        ];
        $expectedParts = [
            'route_key' => 'shipping_method_route',
            'shq_cache_key' => $expectedCacheKey,
            'context_key' => 'sales_channel_context'
        ];

        $event->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn($context);

        $event->expects($this->once())
            ->method('getParts')
            ->willReturn($initialParts);

        $event->expects($this->once())
            ->method('setParts')
            ->with($expectedParts);

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($context->getToken(), $context)
            ->willReturn($cart);

        $this->rateCacheKeyGenerator->expects($this->once())
            ->method('generateKey')
            ->with($cart, $context)
            ->willReturn($expectedCacheKey);

        // Act
        $this->subscriber->shippingMethodRouteCacheKey($event);
    }
}
