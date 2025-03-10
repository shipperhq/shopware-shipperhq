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
use SHQ\RateProvider\Helper\RestHelper;
use ShipperHQ\WS\Client\WebServiceClient;
use ShipperHQ\WS\Rate\Request\RateRequest;
use ShipperHQ\WS\Shared\Credentials;
use ShipperHQ\WS\Shared\SiteDetails;

/**
 * This class is used to interact with the ShipperHQ API
 * 
 * @author Jo Baker
 * @package SHQ\RateProvider\Service
 */
class ShipperHQApiClient
{
    private SystemConfigService $systemConfig;
    private LoggerInterface $logger;
    private RestHelper $restHelper;
    private WebServiceClient $client;

    public function __construct(
        SystemConfigService $systemConfig,
        LoggerInterface $logger,
        RestHelper $restHelper,
        WebServiceClient $client
    ) {
        $this->systemConfig = $systemConfig;
        $this->logger = $logger;
        $this->restHelper = $restHelper;
        $this->client = $client;
    }

    public function getRates(array $context): array
    {
        $request = $this->buildRatesRequest($context);
        $result = $this->sendRequest($request);
        
        if (!$result) {
            return [];
        }
        
        return $result;
    }

    public function getAllowedMethods(): array
    {
        $this->logger->info('SHIPPERHQ: Inside ApiClient getAllowedMethods');
        
        // Create credentials
        $credentials = new Credentials();
        $credentials->apiKey = $this->systemConfig->get('SHQRateProvider.config.apiKey');
        
        // Create site details
        $siteDetails = new SiteDetails();
        $siteDetails->ecommerceCart = 'Shopware';
        $siteDetails->ecommerceVersion = '6.6.0';
        
        // Create rate request
        $request = new RateRequest();
        $request->setCredentials($credentials);
        $request->setSiteDetails($siteDetails);
        
        $result = $this->sendRequest($request, $this->restHelper->getAllowedMethodGatewayUrl());
        
        if (!$result) {
            return [];
        }
        
        return $result;
    }

    private function buildRatesRequest(array $context): RateRequest
    {
        // Create credentials
        $credentials = new Credentials();
        $credentials->apiKey = $this->systemConfig->get('SHQRateProvider.config.apiKey');
        
        // Create site details
        $siteDetails = new SiteDetails();
        $siteDetails->ecommerceCart = 'Shopware';
        $siteDetails->ecommerceVersion = '6.6.0';
        
        // Create rate request
        $request = new RateRequest();
        $request->setCredentials($credentials);
        $request->setSiteDetails($siteDetails);
        
        // Add cart and other context data
        // TODO: Add proper cart and context data mapping
        
        return $request;
    }

    /**
     * Sends the JSON request to ShipperHQ
     *
     * @param RateRequest $request
     * @param string|null $url
     * @return array|null
     */
    private function sendRequest(RateRequest $request, ?string $url = null): ?array
    {
        $timeout = 30;
        $initVal = microtime(true);
        
        $result = $this->client->sendAndReceive(
            $request, 
            $url ?? $this->restHelper->getRateGatewayUrl(),
            $timeout
        );
        
        $elapsed = microtime(true) - $initVal;
        $this->logger->debug('ShipperHQ API request time: ' . $elapsed);

        if (!isset($result['result'])) {
            $this->logger->error('ShipperHQ API Error: No result returned');
            return null;
        }

        $this->logger->debug('ShipperHQ request and result', [
            'request' => $request,
            'result' => $result['debug'] ?? []
        ]);

        return $result['result'];
    }
} 