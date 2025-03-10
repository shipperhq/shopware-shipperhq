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
use ShipperHQ\WS\AllowedMethods\AllowedMethodsRequest;
use ShipperHQ\WS\Shared\Credentials;
use ShipperHQ\WS\Shared\SiteDetails;
use ShipperHQ\WS\Shared\WebServiceRequestInterface;
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
    private \ShipperHQ\Lib\Rate\Helper $shipperHQHelper;

    public function __construct(
        SystemConfigService $systemConfig,
        LoggerInterface $logger,
        RestHelper $restHelper,
        WebServiceClient $client,
        \ShipperHQ\Lib\Rate\Helper $shipperHQHelper
    ) {
        $this->systemConfig = $systemConfig;
        $this->logger = $logger;
        $this->restHelper = $restHelper;
        $this->client = $client;
        $this->shipperHQHelper = $shipperHQHelper;
    }

    public function getRates(array $context): array
    {
        $this->logger->info('SHIPPERHQ: Inside ApiClient getRates');
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
        
        // Create rate request
        $request = new AllowedMethodsRequest();
        $request->setCredentials($this->buildCredentials());
        $request->setSiteDetails($this->buildSiteDetails());
        
        $result = $this->sendRequest($request, $this->restHelper->getAllowedMethodGatewayUrl());

        $resultData = [];
        if (is_object($result['debug'])) {
            $resultData = json_decode(json_encode($resultData), true);
            $this->logger->error($resultData);
        }
        
        $this->logger->info('SHIPPERHQ: Output from getAllowedMethods call', ['result' => $resultData]);

        if (!isset($result['result'])) {

            $this->logger->error('ShipperHQ API Error: No result returned');
            return null; // TODO Tidy this up
        }

        $allowedShippingMethods = [];

        $shipper_response = $this->shipperHQHelper->object_to_array($result);

        $this->logger->info('SHIPPERHQ: Inside ApiClient getAllowedMethods', ['shipper_response' => $shipper_response]);

        // if (isset($shipper_response['carrierGroups'])) {
        //     $transactionId = $this->shipperHQHelper->extractTransactionId($shipper_response);
        //     $allowedShippingMethods = $this->processRatesResponse($shipper_response, $transactionId);
        // } else {
        //     $allowedShippingMethods= [];
        // }

        return $allowedShippingMethods;
    }

    private function buildRatesRequest(array $context): RateRequest
    {
        // Create rate request
        $request = new RateRequest();
        $request->setCredentials($this->buildCredentials());
        $request->setSiteDetails($this->buildSiteDetails());
        
        // Add cart and other context data
        // TODO: Add proper cart and context data mapping
        
        return $request;
    }

    private function buildCredentials(): Credentials
    {
        $credentials = new Credentials();
        $credentials->apiKey = $this->systemConfig->get('SHQRateProvider.config.apiKey');
        $credentials->password = $this->systemConfig->get('SHQRateProvider.config.authenticationCode');

        $this->logger->info('SHIPPERHQ: Credentials', ['credentials' => $credentials]);
        return $credentials;
    }

    private function buildSiteDetails(): SiteDetails
    {
        $siteDetails = new SiteDetails();
        $siteDetails->ecommerceCart = 'Shopware';
        $siteDetails->ecommerceVersion = '6.6.0';
        return $siteDetails;
    }

    /**
     * Sends the JSON request to ShipperHQ
     *
     * @param WebServiceRequestInterface $request
     * @param string|null $url
     * @return array|null
     */
    private function sendRequest($request, ?string $url = null): ?array
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

        $this->logger->debug('ShipperHQ request and result', [
            'request' => $request,
            'result' => $result['result'],
            'debug' => $result['debug']['json_request']  ?? []
        ]);

        // Convert stdClass to array if needed
        // $resultData = $result['result'];
        // if (is_object($resultData)) {
        //     $resultData = json_decode(json_encode($resultData), true);
        // }

        return $result;
    }
} 