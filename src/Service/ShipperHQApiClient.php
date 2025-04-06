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
    private \SHQ\RateProvider\Helper\Mapper $mapper;

    public function __construct(
        SystemConfigService $systemConfig,
        LoggerInterface $logger,
        RestHelper $restHelper,
        WebServiceClient $client,
        \ShipperHQ\Lib\Rate\Helper $shipperHQHelper,
        \SHQ\RateProvider\Helper\Mapper $mapper
    ) {
        $this->systemConfig = $systemConfig;
        $this->logger = $logger;
        $this->restHelper = $restHelper;
        $this->client = $client;
        $this->shipperHQHelper = $shipperHQHelper;
        $this->mapper = $mapper;
    }

    public function getAllowedMethods(): array
    {        
        // Create rate request
        $request = new AllowedMethodsRequest();
        $request->setCredentials($this->buildCredentials());
        $request->setSiteDetails($this->buildSiteDetails());
        
        $result = $this->sendRequest($request, $this->restHelper->getAllowedMethodGatewayUrl());

        $resultData = [];
        if (is_object($result['debug'])) {
            $resultData = json_decode(json_encode($result), true);
            $this->logger->error($resultData);
        }
        

        if (!isset($result['result'])) {

            $this->logger->error('ShipperHQ API Error: No result returned');
            return null; // TODO Tidy this up
        } else {
            $this->logger->info('SHIPPERHQ: Response from getAllowedMethods call', ['result' => $result['result']]);

        }

        $allowedShippingMethods = [];

        $shipper_response = $this->shipperHQHelper->object_to_array($result);

        $this->logger->info('SHIPPERHQ: Formatted Response', ['shipper_response' => $shipper_response]);


        // Check if we have carrier methods in the response
        if (isset($shipper_response['result']) && 
            isset($shipper_response['result']['carrierMethods']) && 
            is_array($shipper_response['result']['carrierMethods'])) {

            //$this->logger->info('SHIPPERHQ: Carrier methods', ['carrierMethods' => $shipper_response['result']['carrierMethods']]);
            // Loop through each carrier
            foreach ($shipper_response['result']['carrierMethods'] as $carrier) {
                $carrierCode = $carrier['carrierCode'];
                $carrierTitle = $carrier['title'];
                
                // Loop through each method for this carrier
                if (isset($carrier['methods']) && is_array($carrier['methods'])) {
                    foreach ($carrier['methods'] as $method) {
                        // Skip if methodCode is not set
                        if (!isset($method['methodCode'])) {
                            continue;
                        }
                        
                        // Add to our allowed methods array
                        $allowedShippingMethods[] = [
                            'carrierCode' => $carrierCode,
                            'carrierTitle' => $carrierTitle,
                            'methodCode' => $method['methodCode'],
                            'methodName' => $method['name']
                        ];
                    }
                }
            }
        }

        $this->logger->debug('SHIPPERHQ: Allowed shipping methods', ['allowedShippingMethods' => $allowedShippingMethods]);

        return $allowedShippingMethods;
    }



    private function buildCredentials(): Credentials
    {
        $credentials = new Credentials();
        $credentials->apiKey = $this->systemConfig->get('SHQRateProvider.config.apiKey');
        $credentials->password = $this->systemConfig->get('SHQRateProvider.config.authenticationCode');

        return $credentials;
    }

    private function buildSiteDetails(): SiteDetails
    {
        $siteDetails = new SiteDetails();
        $siteDetails->ecommerceCart = 'Shopware';
        $siteDetails->ecommerceVersion = '6.6.0';
        $siteDetails->environmentScope = "LIVE";  // Only supporting LIVE for now
        return $siteDetails;
    }

   


    /**
     * TODO Is this required?
     */
    public function getRates(array $context): array
    {
        $this->logger->info('SHIPPERHQ: Inside ApiClient getRates');
        // $request = $this->buildRatesRequest($context);
        // $result = $this->sendRequest($request);
        
      //  if (!$result) {
            return [];
      //  }
        
       // return $result;
    }



    /**
     * Entry point for getting rates for all methods in a single API call
     * 3/11/2025
     */
    public function getRatesForAllMethods(RateRequest $request): ?array
    {
        try {
            // Add credentials and site details if not already set
            if (!$request->getCredentials()) {
                $request->setCredentials($this->buildCredentials());
            }
            
            if (!$request->getSiteDetails()) {
                $request->setSiteDetails($this->buildSiteDetails());
            }

            // Send the request to ShipperHQ
            $result = $this->sendRequest($request, $this->restHelper->getRateGatewayUrl());
            
            // Log the full response for debugging
            $this->logger->debug('SHIPPERHQ: Full API response', [
                'response' => $result
            ]);

            if (!isset($result['result'])) {
                $this->logger->error('ShipperHQ API Error: No result returned');
                return null;
            }

            // Map the response using the Mapper
            $mappedResponse = $this->mapper->mapResponse($result['result']);
            
            // $this->logger->debug('SHIPPERHQ: Mapped response', [
            //     'mapped_response' => $mappedResponse
            // ]);

            return $mappedResponse;
            
        } catch (\Exception $e) {
            $this->logger->error('Error calling ShipperHQ API: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return null;
        } 
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

        // Convert stdClass to array
        if (is_object($result)) {
            $result = json_decode(json_encode($result), true);
        }

        $this->logger->debug('ShipperHQ request and result', [
            'request' => $request,
            'result' => $result['result'] ?? null,
            'debug' => $result['debug']['json_request'] ?? []
        ]);

        return $result;
    }


  

   
    
} 