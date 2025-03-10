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
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use ShipperHQ\GraphQL\Client\GraphQLClient;

class ShippingRateCalculator
{
    private SystemConfigService $systemConfig;
    private LoggerInterface $logger;
    private ShipperHQApiClient $apiClient;

    public function __construct(
        SystemConfigService $systemConfig,
        LoggerInterface $logger,
        ShipperHQApiClient $apiClient
    ) {
        $this->systemConfig = $systemConfig;
        $this->logger = $logger;
        $this->apiClient = $apiClient;
    }

    public function calculateRate(string $shippingMethodId, array $context): ?float
    {
        try {
            $apiKey = $this->systemConfig->get('SHQRateProvider.config.apiKey');
            
            if (!$apiKey) {
                $this->logger->error('ShipperHQ API key not configured');
                return null;
            }

            // Get shipping rates from ShipperHQ
            $rates = $this->apiClient->getRates($context);

            // Find matching rate for shipping method
            foreach ($rates as $rate) {
                if ($rate['methodCode'] === $shippingMethodId) {
                    return (float) $rate['price'];
                }
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->error('Error calculating shipping rate: ' . $e->getMessage(), [
                'exception' => $e,
                'shippingMethodId' => $shippingMethodId
            ]);
            return null;
        }
    }
} 