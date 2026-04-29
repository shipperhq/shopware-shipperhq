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

use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class SessionRateStorage
{
    private const CACHE_KEY_PREFIX = 'shipperhq_shipping_rates';
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_TAG = 'shipperhq_rates';

    public function __construct(
        private readonly TagAwareAdapterInterface $cache,
    ) {}

    public function has(string $cacheKey): bool
    {
        try {
            $item = $this->cache->getItem($this->normalizeCacheKey($cacheKey));
            return $item->isHit();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function get(string $cacheKey): array
    {
        $cachedData = $this->getCachedData($cacheKey);
        return $cachedData['rates'] ?? [];
    }

    public function getCachedData(string $cacheKey): array
    {
        try {
            $item = $this->cache->getItem($this->normalizeCacheKey($cacheKey));
            
            if ($item->isHit()) {
                return $item->get();
            }
        } catch (\Exception $e) {
            // If cache retrieval fails, return empty default
        }
        
        return ['timestamp' => 0, 'rates' => []];
    }

    public function set(string $cacheKey, array $rates): void
    {
        if (empty($rates)) {
            return;
        }

        $cacheData = [
            'timestamp' => time(),
            'rates' => $rates
        ];

        try {
            $item = $this->cache->getItem($this->normalizeCacheKey($cacheKey));
            $item->set($cacheData);
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(self::CACHE_TAG);
            $this->cache->save($item);
        } catch (\Exception $e) {
            // Silently fail if cache write fails
        }
    }

    public function clear(): void
    {
        try {
            $this->cache->invalidateTags([self::CACHE_TAG]);
        } catch (\Exception $e) {
            // Silently fail if cache clear fails
        }
    }

    /**
     * Normalize cache key to be PSR-6 compliant
     * Cache keys must not contain: {}()/\@:
     */
    private function normalizeCacheKey(string $key): string
    {
        return self::CACHE_KEY_PREFIX . '.' . str_replace(
            ['@', ':', '\\', '/', '{', '}', '(', ')'],
            ['_', '_', '_', '_', '_', '_', '_', '_'],
            $key
        );
    }
}
