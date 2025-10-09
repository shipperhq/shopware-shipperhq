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

namespace SHQ\RateProvider\Tests\Integration\Feature\Checkout\Rating\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheKeyEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SHQ\RateProvider\Feature\Checkout\Rating\Subscriber\ShippingMethodRouteSubscriber;

class ShippingMethodRouteSubscriberIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ShippingMethodRouteSubscriber $subscriber;
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->subscriber = $this->getContainer()->get(ShippingMethodRouteSubscriber::class);
        $this->salesChannelContext = $this->createSalesChannelContext();
    }

    public function testShippingMethodRouteCacheKeyEventIntegration(): void
    {
        // Arrange
        $cart = $this->createTestCart();
        $criteria = new Criteria();
        
        $event = new ShippingMethodRouteCacheKeyEvent(
            $criteria,
            $this->salesChannelContext,
            ['initial_key' => 'initial_value']
        );

        // Act
        $this->subscriber->shippingMethodRouteCacheKey($event);

        // Assert
        $parts = $event->getParts();
        $this->assertArrayHasKey('shq_cache_key', $parts);
        $this->assertStringStartsWith('shipperhq_shipping_rates_', $parts['shq_cache_key']);
        $this->assertArrayHasKey('initial_key', $parts);
        $this->assertEquals('initial_value', $parts['initial_key']);
    }

    public function testShippingMethodRouteCacheKeyEventWithEmptyCart(): void
    {
        // Arrange
        $criteria = new Criteria();
        $event = new ShippingMethodRouteCacheKeyEvent(
            $criteria,
            $this->salesChannelContext,
            ['test_key' => 'test_value']
        );

        // Act
        $this->subscriber->shippingMethodRouteCacheKey($event);

        // Assert
        $parts = $event->getParts();
        // Should not add SHQ cache key when no cart is available
        $this->assertArrayNotHasKey('shq_cache_key', $parts);
        $this->assertArrayHasKey('test_key', $parts);
        $this->assertEquals('test_value', $parts['test_key']);
    }

    public function testShippingMethodRouteCacheKeyEventConsistency(): void
    {
        // Arrange
        $cart = $this->createTestCart();
        $criteria = new Criteria();
        
        $event1 = new ShippingMethodRouteCacheKeyEvent(
            $criteria,
            $this->salesChannelContext,
            ['key1' => 'value1']
        );
        
        $event2 = new ShippingMethodRouteCacheKeyEvent(
            $criteria,
            $this->salesChannelContext,
            ['key1' => 'value1']
        );

        // Act
        $this->subscriber->shippingMethodRouteCacheKey($event1);
        $this->subscriber->shippingMethodRouteCacheKey($event2);

        // Assert
        $parts1 = $event1->getParts();
        $parts2 = $event2->getParts();
        
        // Both should have SHQ cache key
        $this->assertArrayHasKey('shq_cache_key', $parts1);
        $this->assertArrayHasKey('shq_cache_key', $parts2);
        
        // Cache keys should be identical for the same cart and context
        $this->assertEquals($parts1['shq_cache_key'], $parts2['shq_cache_key']);
    }

    public function testShippingMethodRouteCacheKeyEventWithDifferentCarts(): void
    {
        // Arrange
        $cart1 = $this->createTestCart();
        $cart2 = $this->createTestCartWithDifferentItems();
        $criteria = new Criteria();
        
        $event1 = new ShippingMethodRouteCacheKeyEvent(
            $criteria,
            $this->salesChannelContext,
            ['key' => 'value']
        );
        
        $event2 = new ShippingMethodRouteCacheKeyEvent(
            $criteria,
            $this->salesChannelContext,
            ['key' => 'value']
        );

        // Mock the cart service to return different carts
        $cartService = $this->getContainer()->get('Shopware\Core\Checkout\Cart\SalesChannel\CartService');
        $this->getContainer()->set('Shopware\Core\Checkout\Cart\SalesChannel\CartService', 
            new class($cart1, $cart2) {
                private $cart1;
                private $cart2;
                private $callCount = 0;
                
                public function __construct($cart1, $cart2) {
                    $this->cart1 = $cart1;
                    $this->cart2 = $cart2;
                }
                
                public function getCart($token, $context) {
                    return $this->callCount++ === 0 ? $this->cart1 : $this->cart2;
                }
            }
        );

        // Act
        $this->subscriber->shippingMethodRouteCacheKey($event1);
        $this->subscriber->shippingMethodRouteCacheKey($event2);

        // Assert
        $parts1 = $event1->getParts();
        $parts2 = $event2->getParts();
        
        // Both should have SHQ cache key
        $this->assertArrayHasKey('shq_cache_key', $parts1);
        $this->assertArrayHasKey('shq_cache_key', $parts2);
        
        // Cache keys should be different for different carts
        $this->assertNotEquals($parts1['shq_cache_key'], $parts2['shq_cache_key']);
    }

    private function createTestCart(): Cart
    {
        $cart = new Cart('test-cart-token');
        
        $lineItem = new LineItem('test-item-1', LineItem::PRODUCT_LINE_ITEM_TYPE, 'test-product-1');
        $lineItem->setQuantity(2);
        $lineItem->setPrice(new CalculatedPrice(
            10.00,
            20.00,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        ));
        
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        
        return $cart;
    }

    private function createTestCartWithDifferentItems(): Cart
    {
        $cart = new Cart('test-cart-token-2');
        
        $lineItem = new LineItem('test-item-2', LineItem::PRODUCT_LINE_ITEM_TYPE, 'test-product-2');
        $lineItem->setQuantity(1);
        $lineItem->setPrice(new CalculatedPrice(
            15.00,
            15.00,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        ));
        
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        
        return $cart;
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        
        return $contextFactory->create('test-token', 'test-sales-channel-id');
    }
}
