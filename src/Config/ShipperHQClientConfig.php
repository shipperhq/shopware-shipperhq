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

namespace SHQ\RateProvider\Config;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ShipperHQClientConfig
{
    private const TEST_URL = 'http://localhost:8080/shipperhq-ws/v1/';
    private const LIVE_URL = 'https://api.shipperhq.com/v1/';

    private SystemConfigService $systemConfig;

    public function __construct(SystemConfigService $systemConfig)
    {
        $this->systemConfig = $systemConfig;
    }

    public function getGatewayUrl(): string
    {
        return $this->isDeveloperMode() ? self::TEST_URL : self::LIVE_URL;
    }

    public function isDeveloperMode(): bool
    {
        return $this->systemConfig->get('SHQRateProvider.config.developerMode') ?? false;
    }

    public function getAllowedMethodsUrl(): string
    {
        return $this->getGatewayUrl() . 'allowed_methods';
    }

    public function getRatesUrl(): string
    {
        return $this->getGatewayUrl() . 'rates';
    }

    public function getAttributesUrl(): string
    {
        return $this->getGatewayUrl() . 'attributes/get';
    }

    public function getCheckSynchronizedUrl(): string
    {
        return $this->getGatewayUrl() . 'attributes/check';
    }

    public function getApiKey(): ?string
    {
        return $this->systemConfig->get('SHQRateProvider.config.apiKey');
    }

    public function getAuthenticationCode(): ?string
    {
        return $this->systemConfig->get('SHQRateProvider.config.authenticationCode');
    }
}
