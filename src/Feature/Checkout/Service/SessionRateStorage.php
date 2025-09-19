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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionRateStorage 
{
    private const CACHE_KEY = 'shipperhq_shipping_rates';
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getSession(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            return $request->getSession();
        }
        // Fallback to main request's session if available
        $mainRequest = $this->requestStack->getMainRequest();
        if ($mainRequest && $mainRequest->hasSession()) {
            return $mainRequest->getSession();
        }
        // As a last resort, create a new session to avoid null issues
        // but do NOT save/commit here to prevent cookie resets
        return new \Symfony\Component\HttpFoundation\Session\Session();
    }

    public function has(string $cacheKey): bool
    {
        return $this->getSession()->has($cacheKey);
    }

    public function get(string $cacheKey): array
    {
        $cachedData = $this->getCachedData($cacheKey);
        return $cachedData['rates'] ?? [];
    }

    public function getCachedData(string $cacheKey): array
    {
        return $this->getSession()->get($cacheKey, ['timestamp' => 0, 'rates' => []]);
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

        $this->getSession()->set($cacheKey, $cacheData);
    }

    public function clear(): void
    {
        $session = $this->getSession();
        $sessionKeys = [];

        foreach ($session->all() as $key => $value) {
            if (strpos($key, self::CACHE_KEY) === 0) {
                $sessionKeys[] = $key;
            }
        }

        foreach ($sessionKeys as $key) {
            $session->remove($key);
        }

        // Do not explicitly save here; the framework manages session persistence
    }
}
