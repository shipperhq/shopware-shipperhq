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

namespace SHQ\RateProvider\Feature\Checkout\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ShippingRateCache
{
    private const CACHE_LIFETIME = 300; // 5 minutes in seconds

    private SessionRateStorage $sessionRateStorage;
    private RateMatcher $rateMatcher;
    private RateCacheKeyGenerator $rateCacheKeyGenerator;
    private ShipperHQRateProvider $rateProvider;
    private LoggerInterface $logger;

    public function __construct(
        SessionRateStorage $sessionRateStorage,
        RateMatcher $rateMatcher,
        RateCacheKeyGenerator $rateCacheKeyGenerator,
        ShipperHQRateProvider $rateProvider,
        LoggerInterface $logger
    ) {
        $this->sessionRateStorage = $sessionRateStorage;
        $this->rateMatcher = $rateMatcher;
        $this->rateCacheKeyGenerator = $rateCacheKeyGenerator;
        $this->rateProvider = $rateProvider;
        $this->logger = $logger;
    }

    /**
     * Get the session
     *
     * @return SessionInterface
     */
    private function getSession(): SessionInterface
    {
        return $this->sessionRateStorage->getSession();
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
        $cacheKey = $this->rateCacheKeyGenerator->generateKey($cart, $context);
        $cachedData = $this->sessionRateStorage->getCachedData($cacheKey);

        if (!empty($cachedData['rates']) && $this->isCacheValid($cachedData)) {
            $this->logger->debug('Using cached rates for key: ' . $cacheKey);
            return $cachedData['rates'];
        }

        $this->logger->debug('No cached rates found or cache expired for key: ' . $cacheKey);
        $rates = $this->rateProvider->getBatchRates($cart, $context) ?? [];
        if (!empty($rates)) {
            $this->sessionRateStorage->set($cacheKey, $rates);
        }

        return $rates;
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
        $this->logger->info('SHIPPERHQ: Getting rate for method', ['method_id' => $shippingMethodId]);
        
        $rates = $this->getRates($cart, $context);
        
        $this->logger->info('SHIPPERHQ: Got rates for method', [
            'method_id' => $shippingMethodId,
            'rates' => $rates
        ]);
        
        return $this->rateMatcher->findRateForMethod($shippingMethodId, $rates, $context);
    }

    /**
     * Clear the rate cache
     */
    public function clearCache(): void
    {
        $this->sessionRateStorage->clear();
    }

    private function hasValidCachedRates(string $cacheKey): bool
    {
        if (!$this->sessionRateStorage->has($cacheKey)) {
            return false;
        }

        $cachedData = $this->sessionRateStorage->getCachedData($cacheKey);
        return $this->isCacheValid($cachedData) && !empty($cachedData['rates']);
    }

    private function isCacheValid(array $cachedData): bool
    {
        if (!isset($cachedData['timestamp']) || !isset($cachedData['rates'])) {
            return false;
        }
        
        return (time() - $cachedData['timestamp']) < self::CACHE_LIFETIME;
    }
}
