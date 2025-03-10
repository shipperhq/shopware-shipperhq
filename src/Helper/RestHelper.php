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

namespace SHQ\RateProvider\Helper;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class RestHelper
{
    private const TEST_URL = 'http://www.localhost.com:8080/shipperhq-ws/v1/';
    private const LIVE_URL = 'http://api.shipperhq.com/v1/';

    private SystemConfigService $systemConfig;

    public function __construct(SystemConfigService $systemConfig)
    {
        $this->systemConfig = $systemConfig;
    }

    protected function getGatewayUrl(): string
    {
        return $this->getDeveloperMode() ? self::TEST_URL : self::LIVE_URL;
    }

    private function getDeveloperMode(): bool
    {
        return $this->systemConfig->get('SHQRateProvider.config.developerMode') ?? false;
    }

    public function getAllowedMethodGatewayUrl(): string
    {
        return $this->getGatewayUrl() . 'allowed_methods';
    }

    public function getRateGatewayUrl(): string
    {
        return $this->getGatewayUrl() . 'rates';
    }

    public function getAttributeGatewayUrl(): string
    {
        return $this->getGatewayUrl() . 'attributes/get';
    }

    public function getCheckSynchronizedUrl(): string
    {
        return $this->getGatewayUrl() . 'attributes/check';
    }
} 