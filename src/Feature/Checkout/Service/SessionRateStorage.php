<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Checkout\Service;

use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionRateStorage 
{
    private const CACHE_KEY = 'shipperhq_shipping_rates';
    private SessionFactoryInterface $sessionFactory;

    public function __construct(SessionFactoryInterface $sessionFactory)
    {
        $this->sessionFactory = $sessionFactory;
    }

    public function getSession(): SessionInterface
    {
        return $this->sessionFactory->createSession();
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

        $session->save();
    }
}
