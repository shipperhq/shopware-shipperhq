<?php declare(strict_types=1);

/**
 * Shipper HQ
 *
 * @category ShipperHQ
 * @package shopware-shipperhq
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license ShipperHQ 2025
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace SHQ\RateProvider\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ShippingRateCache
{
    private const CACHE_KEY = 'shipperhq_shipping_rates';
    private const CACHE_LIFETIME = 3600; // 1 hour in seconds
    
    private SessionFactoryInterface $sessionFactory;
    private LoggerInterface $logger;
    private ShipperHQBatchRateProvider $batchRateProvider;

    public function __construct(
        SessionFactoryInterface $sessionFactory,
        LoggerInterface $logger,
        ShipperHQBatchRateProvider $batchRateProvider
    ) {
        $this->sessionFactory = $sessionFactory;
        $this->logger = $logger;
        $this->batchRateProvider = $batchRateProvider;
    }

    /**
     * Get the session
     *
     * @return SessionInterface
     */
    private function getSession(): SessionInterface
    {
        return $this->sessionFactory->createSession();
    }

    /**
     * Get cached rates or fetch new ones if needed
     *
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return array
     */
    public function getRates(Cart $cart, SalesChannelContext $context): array
    {
        $cacheKey = $this->generateCacheKey($cart, $context);
        
        // Check if we have cached rates that are still valid
        if ($this->hasCachedRates($cacheKey)) {
            $cachedData = $this->getCachedRates($cacheKey);
            
            // Check if the cache is still valid and rates are not empty
            if ($this->isCacheValid($cachedData) && !empty($cachedData['rates'])) {
                $this->logger->debug('Using cached shipping rates', [
                    'cacheKey' => $cacheKey
                ]);
                return $cachedData['rates'];
            }
            
            // If cache is valid but rates are empty, clear the cache and try again
            $this->logger->debug('Cached rates are empty, clearing cache and fetching new rates');
            $this->clearCache();
        }
        
        // Fetch new rates
        $this->logger->debug('Fetching new shipping rates from ShipperHQ');
        $rates = $this->batchRateProvider->getBatchRates($cart, $context);
        
        // Cache the rates if we got any
        if ($rates !== null && !empty($rates)) {
            $this->cacheRates($cacheKey, $rates);
            return $rates;
        }
        
        $this->logger->warning('No shipping rates returned from ShipperHQ');
        return [];
    }

    /**
     * Get rate for a specific shipping method
     *
     * @param string $shippingMethodId
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return float|null
     */
    public function getRateForMethod(string $shippingMethodId, Cart $cart, SalesChannelContext $context): ?float
    {
        $rates = $this->getRates($cart, $context);
        
        foreach ($rates as $rate) {
            if ($rate['methodCode'] === $shippingMethodId) {
                return (float) $rate['price'];
            }
        }
        
        $this->logger->debug('No rate found for shipping method', [
            'shippingMethodId' => $shippingMethodId
        ]);
        
        return null;
    }

    /**
     * Clear the rate cache
     */
    public function clearCache(): void
    {
        $this->logger->debug('Clearing shipping rate cache');
        
        // Get all session keys
        $sessionKeys = [];
        foreach ($this->getSession()->all() as $key => $value) {
            if (strpos($key, self::CACHE_KEY) === 0) {
                $sessionKeys[] = $key;
            }
        }
        
        // Remove all cache entries
        foreach ($sessionKeys as $key) {
            $this->getSession()->remove($key);
        }
    }

    /**
     * Generate a cache key based on cart contents and context
     *
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return string
     */
    private function generateCacheKey(Cart $cart, SalesChannelContext $context): string
    {
        // Get customer address hash
        $addressHash = $this->getAddressHash($context);
        
        // Get cart items hash
        $cartItemsHash = $this->getCartItemsHash($cart);
        
        // Include currency and customer group in the cache key
        $currencyId = $context->getCurrency()->getId();
        $customerGroupId = $context->getCurrentCustomerGroup()->getId();
        
        // Create a unique cache key
        return self::CACHE_KEY . '_' . md5(
            $addressHash . '_' . 
            $cartItemsHash . '_' . 
            $currencyId . '_' . 
            $customerGroupId
        );
    }

    /**
     * Generate a hash of the shipping address
     *
     * @param SalesChannelContext $context
     * @return string
     */
    private function getAddressHash(SalesChannelContext $context): string
    {
        $customer = $context->getCustomer();
        
        if (!$customer) {
            return 'guest';
        }
        
        $shippingAddress = $customer->getActiveShippingAddress();
        
        if (!$shippingAddress) {
            return 'no_address';
        }
        
        // Create a hash of the address fields that affect shipping
        return md5(
            $shippingAddress->getCountry()->getIso() . '_' .
            ($shippingAddress->getCountryState() ? $shippingAddress->getCountryState()->getShortCode() : '') . '_' .
            $shippingAddress->getZipcode() . '_' .
            $shippingAddress->getCity() . '_' .
            $shippingAddress->getStreet()
        );
    }

    /**
     * Generate a hash of the cart items
     *
     * @param Cart $cart
     * @return string
     */
    private function getCartItemsHash(Cart $cart): string
    {
        $itemsData = [];
        
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }
            
            $itemsData[] = [
                'id' => $lineItem->getId(),
                'quantity' => $lineItem->getQuantity(),
                'price' => $lineItem->getPrice() ? $lineItem->getPrice()->getTotalPrice() : 0,
                'weight' => $this->getItemWeight($lineItem)
            ];
        }
        
        // Sort the items to ensure consistent hash regardless of item order
        usort($itemsData, function ($a, $b) {
            return strcmp($a['id'], $b['id']);
        });
        
        return md5(json_encode($itemsData));
    }

    /**
     * Get item weight from line item
     *
     * @param LineItem $lineItem
     * @return float
     */
    private function getItemWeight(LineItem $lineItem): float
    {
        // Try to get weight from delivery information
        if ($lineItem->getDeliveryInformation() && $lineItem->getDeliveryInformation()->getWeight()) {
            return $lineItem->getDeliveryInformation()->getWeight();
        }
        
        // Try to get weight from payload
        if ($lineItem->hasPayloadValue('weight')) {
            return (float) $lineItem->getPayloadValue('weight');
        }
        
        // Default weight
        return 0.0;
    }

    /**
     * Check if we have cached rates for the given key
     *
     * @param string $cacheKey
     * @return bool
     */
    private function hasCachedRates(string $cacheKey): bool
    {
        return $this->getSession()->has($cacheKey);
    }

    /**
     * Get cached rates for the given key
     *
     * @param string $cacheKey
     * @return array
     */
    private function getCachedRates(string $cacheKey): array
    {
        return $this->getSession()->get($cacheKey, ['timestamp' => 0, 'rates' => []]);
    }

    /**
     * Cache rates for the given key
     *
     * @param string $cacheKey
     * @param array $rates
     */
    private function cacheRates(string $cacheKey, array $rates): void
    {
        $cacheData = [
            'timestamp' => time(),
            'rates' => $rates
        ];
        
        $this->getSession()->set($cacheKey, $cacheData);
        
        $this->logger->debug('Cached shipping rates', [
            'cacheKey' => $cacheKey,
            'rateCount' => count($rates)
        ]);
    }

    /**
     * Check if the cached data is still valid
     *
     * @param array $cachedData
     * @return bool
     */
    private function isCacheValid(array $cachedData): bool
    {
        if (!isset($cachedData['timestamp']) || !isset($cachedData['rates'])) {
            return false;
        }
        
        $timestamp = $cachedData['timestamp'];
        $now = time();
        
        // Check if the cache has expired
        return ($now - $timestamp) < self::CACHE_LIFETIME;
    }
} 