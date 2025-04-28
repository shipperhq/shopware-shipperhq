<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Checkout\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class RateMatcher
{
    private EntityRepository $shippingMethodRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $shippingMethodRepository,
        LoggerInterface $logger
    ) {
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->logger = $logger;
    }

    public function findRateForMethod(string $shippingMethodId, array $rates, SalesChannelContext $context): ?float
    {
        $this->logger->info('SHIPPERHQ: Getting rate for method', ['method_id' => $shippingMethodId]);

        if (isset($rates[$shippingMethodId]) && isset($rates[$shippingMethodId]['price'])) {
            $this->logger->debug('Found rate by shipping method ID', [
                'method_id' => $shippingMethodId,
                'price' => $rates[$shippingMethodId]['price']
            ]);
            return (float) $rates[$shippingMethodId]['price'];
        }

        $shippingMethod = $this->getShippingMethod($shippingMethodId, $context);
        if (!$shippingMethod) {
            return null;
        }

        $rate = $this->matchByCustomFields($shippingMethod, $rates) 
            ?? $this->matchByTechnicalName($shippingMethod, $rates);

        if ($rate === null) {
            $this->logNoRateFound($shippingMethodId, $shippingMethod->getTechnicalName(), $rates);
        }

        return $rate;
    }

    private function getShippingMethod(string $shippingMethodId, SalesChannelContext $context)
    {
        $criteria = new Criteria([$shippingMethodId]);
        $shippingMethod = $this->shippingMethodRepository->search($criteria, $context->getContext())->first();

        if (!$shippingMethod) {
            $this->logger->warning('Shipping method not found', ['method_id' => $shippingMethodId]);
            return null;
        }

        return $shippingMethod;
    }

    private function matchByCustomFields($shippingMethod, array $rates): ?float
    {
        $customFields = $shippingMethod->getCustomFields() ?? [];
        if (!isset($customFields['shipperhq_carrier_code']) || !isset($customFields['shipperhq_method_code'])) {
            return null;
        }

        $carrierCode = $customFields['shipperhq_carrier_code'];
        $methodCode = $customFields['shipperhq_method_code'];

        foreach ($rates as $rate) {
            if (isset($rate['carrierCode']) && isset($rate['methodCode']) &&
                strtolower($rate['carrierCode']) === strtolower($carrierCode) && 
                $rate['methodCode'] === $methodCode) {
                
                $this->logger->debug('Found matching rate by custom fields', [
                    'carrier_code' => $carrierCode,
                    'method_code' => $methodCode,
                    'price' => $rate['price'] ?? null
                ]);
                
                return isset($rate['price']) ? (float) $rate['price'] : null;
            }
        }

        return null;
    }

    private function matchByTechnicalName($shippingMethod, array $rates): ?float
    {
        $technicalName = $shippingMethod->getTechnicalName();
        if (!str_starts_with($technicalName, 'shq')) {
            return null;
        }

        $parts = explode('-', substr($technicalName, 3));
        if (count($parts) !== 2) {
            return null;
        }

        $carrierCode = $parts[0];
        $methodCode = $parts[1];

        foreach ($rates as $rate) {
            if (isset($rate['carrierCode']) && isset($rate['methodCode']) &&
                strtolower($rate['carrierCode']) === strtolower($carrierCode) && 
                $rate['methodCode'] === $methodCode) {
                
                $this->logger->debug('Found matching rate by technical name', [
                    'technical_name' => $technicalName,
                    'carrier_code' => $carrierCode,
                    'method_code' => $methodCode,
                    'price' => $rate['price'] ?? null
                ]);
                
                return isset($rate['price']) ? (float) $rate['price'] : null;
            }
        }

        return null;
    }

    private function logNoRateFound(string $shippingMethodId, ?string $technicalName, array $rates): void
    {
        $this->logger->debug('No rate found for shipping method', [
            'method_id' => $shippingMethodId,
            'technical_name' => $technicalName,
            'available_rates' => array_map(function($rate) {
                return [
                    'carrier_code' => $rate['carrierCode'] ?? null,
                    'method_code' => $rate['methodCode'] ?? null,
                    'price' => $rate['price'] ?? null
                ];
            }, $rates)
        ]);
    }
}
