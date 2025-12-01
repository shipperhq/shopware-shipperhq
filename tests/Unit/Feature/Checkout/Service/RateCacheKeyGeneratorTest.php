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

namespace SHQ\RateProvider\Tests\Unit\Feature\Checkout\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SHQ\RateProvider\Feature\Checkout\Service\RateCacheKeyGenerator;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Customer\CustomerEntity;
use Shopware\Core\Customer\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Country\CountryStateEntity;

class RateCacheKeyGeneratorTest extends TestCase
{
    private RateCacheKeyGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new RateCacheKeyGenerator();
    }

    public function testGenerateKeyWithGuestCustomer(): void
    {
        // Arrange
        $cart = $this->createTestCart();
        $context = $this->createSalesChannelContextWithGuestCustomer();

        // Act
        $key = $this->generator->generateKey($cart, $context);

        // Assert
        $this->assertStringStartsWith('shipperhq_shipping_rates_', $key);
        $this->assertIsString($key);
    }

    public function testGenerateKeyWithCustomerAndAddress(): void
    {
        // Arrange
        $cart = $this->createTestCart();
        $context = $this->createSalesChannelContextWithCustomer();

        // Act
        $key = $this->generator->generateKey($cart, $context);

        // Assert
        $this->assertStringStartsWith('shipperhq_shipping_rates_', $key);
        $this->assertIsString($key);
    }

    public function testGenerateKeyConsistency(): void
    {
        // Arrange
        $cart = $this->createTestCart();
        $context = $this->createSalesChannelContextWithCustomer();

        // Act
        $key1 = $this->generator->generateKey($cart, $context);
        $key2 = $this->generator->generateKey($cart, $context);

        // Assert
        $this->assertEquals($key1, $key2);
    }

    public function testGenerateKeyDifferentCarts(): void
    {
        // Arrange
        $cart1 = $this->createTestCart();
        $cart2 = $this->createTestCartWithDifferentItems();
        $context = $this->createSalesChannelContextWithCustomer();

        // Act
        $key1 = $this->generator->generateKey($cart1, $context);
        $key2 = $this->generator->generateKey($cart2, $context);

        // Assert
        $this->assertNotEquals($key1, $key2);
    }

    public function testGenerateKeyDifferentContexts(): void
    {
        // Arrange
        $cart = $this->createTestCart();
        $context1 = $this->createSalesChannelContextWithCustomer();
        $context2 = $this->createSalesChannelContextWithDifferentCustomer();

        // Act
        $key1 = $this->generator->generateKey($cart, $context1);
        $key2 = $this->generator->generateKey($cart, $context2);

        // Assert
        $this->assertNotEquals($key1, $key2);
    }

    public function testGenerateKeyWithEmptyCart(): void
    {
        // Arrange
        $cart = new Cart('test-token');
        $context = $this->createSalesChannelContextWithCustomer();

        // Act
        $key = $this->generator->generateKey($cart, $context);

        // Assert
        $this->assertStringStartsWith('shipperhq_shipping_rates_', $key);
        $this->assertIsString($key);
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

    private function createSalesChannelContextWithGuestCustomer(): SalesChannelContext|MockObject
    {
        $context = $this->createMock(SalesChannelContext::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $customerGroup = $this->createMock(CustomerGroupEntity::class);

        $currency->method('getId')->willReturn('currency-id-1');
        $customerGroup->method('getId')->willReturn('customer-group-id-1');

        $context->method('getCurrency')->willReturn($currency);
        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);
        $context->method('getCustomer')->willReturn(null);

        return $context;
    }

    private function createSalesChannelContextWithCustomer(): SalesChannelContext|MockObject
    {
        $context = $this->createMock(SalesChannelContext::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $customerGroup = $this->createMock(CustomerGroupEntity::class);
        $customer = $this->createMock(CustomerEntity::class);
        $address = $this->createMock(CustomerAddressEntity::class);
        $country = $this->createMock(CountryEntity::class);
        $countryState = $this->createMock(CountryStateEntity::class);

        $currency->method('getId')->willReturn('currency-id-1');
        $customerGroup->method('getId')->willReturn('customer-group-id-1');
        $country->method('getIso')->willReturn('US');
        $countryState->method('getShortCode')->willReturn('CA');
        $address->method('getCountry')->willReturn($country);
        $address->method('getCountryState')->willReturn($countryState);
        $address->method('getZipcode')->willReturn('12345');
        $address->method('getCity')->willReturn('Test City');
        $address->method('getStreet')->willReturn('123 Test Street');
        $customer->method('getActiveShippingAddress')->willReturn($address);
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);
        $context->method('getCustomer')->willReturn($customer);

        return $context;
    }

    private function createSalesChannelContextWithDifferentCustomer(): SalesChannelContext|MockObject
    {
        $context = $this->createMock(SalesChannelContext::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $customerGroup = $this->createMock(CustomerGroupEntity::class);
        $customer = $this->createMock(CustomerEntity::class);
        $address = $this->createMock(CustomerAddressEntity::class);
        $country = $this->createMock(CountryEntity::class);
        $countryState = $this->createMock(CountryStateEntity::class);

        $currency->method('getId')->willReturn('currency-id-2');
        $customerGroup->method('getId')->willReturn('customer-group-id-2');
        $country->method('getIso')->willReturn('CA');
        $countryState->method('getShortCode')->willReturn('ON');
        $address->method('getCountry')->willReturn($country);
        $address->method('getCountryState')->willReturn($countryState);
        $address->method('getZipcode')->willReturn('M5V 3A8');
        $address->method('getCity')->willReturn('Toronto');
        $address->method('getStreet')->willReturn('456 Different Street');
        $customer->method('getActiveShippingAddress')->willReturn($address);
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);
        $context->method('getCustomer')->willReturn($customer);

        return $context;
    }
}
