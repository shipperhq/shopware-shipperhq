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

namespace SHQ\RateProvider\Service;

use Psr\Log\LoggerInterface;
use ShipperHQ\Lib\Rate\Helper;
use ShipperHQ\WS\AllowedMethods\AllowedMethodsRequest;
use ShipperHQ\WS\Client\WebServiceClient;
use ShipperHQ\WS\Rate\Request\RateRequest;
use ShipperHQ\WS\Rate\Response\RateResponse;
use ShipperHQ\WS\WebServiceRequestInterface;
use SHQ\RateProvider\Config\ShipperHQClientConfig;
use SHQ\RateProvider\Helper\Mapper;

/**
 * This class is used to interact with the ShipperHQ API
 * 
 * @author Jo Baker
 * @package SHQ\RateProvider\Service
 */
class ShipperHQClient
{
    public function __construct(
        private ShipperHQClientConfig $config,
        private LoggerInterface $logger,
        private WebServiceClient $client,
        private Helper $shipperHQHelper,
        private Mapper $mapper
    ) {}

    public function getAllowedMethods(): array
    {        
        $request = new AllowedMethodsRequest();
        $request->setCredentials($this->mapper->getCredentials());
        $request->setSiteDetails($this->mapper->getSiteDetails());
        
        $result = $this->sendRequest($request, $this->config->getAllowedMethodsUrl());

        if (is_object($result['debug'])) {
            $resultData = json_decode(json_encode($result), true);
            $this->logger->error($resultData);
        }

        if (!isset($result['result'])) {
            $this->logger->error('ShipperHQ API Error: No result returned');
            return [];
        } else {
            $this->logger->info('SHIPPERHQ: Response from getAllowedMethods call', ['result' => $result['result']]);
        }

        $allowedShippingMethods = [];
        $shipper_response = $this->shipperHQHelper->object_to_array($result);
        $this->logger->info('SHIPPERHQ: Formatted Response', ['shipper_response' => $shipper_response]);

        if (isset($shipper_response['result']) && 
            isset($shipper_response['result']['carrierMethods']) && 
            is_array($shipper_response['result']['carrierMethods'])) {
            foreach ($shipper_response['result']['carrierMethods'] as $carrier) {
                $carrierCode = $carrier['carrierCode'];
                $carrierTitle = $carrier['title'];
                
                if (isset($carrier['methods']) && is_array($carrier['methods'])) {
                    foreach ($carrier['methods'] as $method) {
                        if (!isset($method['methodCode'])) {
                            continue;
                        }
                        
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

    /**
     * Entry point for getting rates for all methods in a single API call
     * 3/11/2025
     */
    public function getRatesForAllMethods(RateRequest $request): ?RateResponse
    {
        try {
            // Add credentials and site details if not already set
            if (!$request->getCredentials()) {
                $request->setCredentials($this->mapper->getCredentials());
            }
            
            if (!$request->getSiteDetails()) {
                $request->setSiteDetails($this->mapper->getSiteDetails());
            }

            $result = $this->sendRequest($request, $this->config->getRatesUrl());
            
            $this->logger->debug('SHIPPERHQ: Full API response', [
                'response' => $result
            ]);

            if (!isset($result['result'])) {
                $this->logger->error('ShipperHQ API Error: No result returned');
                return null;
            }

            // Check if the result is an object and convert it to an array if needed
            $resultData = $result['result'];
            if (is_object($resultData)) {
                $this->logger->debug('SHIPPERHQ: Converting result object to array', [
                    'result_type' => gettype($resultData)
                ]);
                $resultData = json_decode(json_encode($resultData), true);
            }

            // Check if the result has the expected structure
            if (!isset($resultData['carrierGroups']) && !isset($resultData['mergedRateResponse'])) {
                $this->logger->warning('SHIPPERHQ: Result does not have expected structure', [
                    'result_type' => gettype($resultData),
                    'result_keys' => is_array($resultData) ? array_keys($resultData) : 'Not an array'
                ]);
            }

            // Map the response using the Mapper
            $mappedResponse = $this->mapper->mapResponse($resultData);
            
            $this->logger->debug('SHIPPERHQ: Mapped response', [
                'has_errors' => $mappedResponse->getErrors() && count($mappedResponse->getErrors()) > 0,
                'has_carrier_groups' => $mappedResponse->getCarrierGroupResponses() && count($mappedResponse->getCarrierGroupResponses()) > 0,
                'carrier_groups_count' => $mappedResponse->getCarrierGroupResponses() ? count($mappedResponse->getCarrierGroupResponses()) : 0
            ]);

            return $mappedResponse;
            
        } catch (\Exception $e) {
            $this->logger->error('Error calling ShipperHQ API: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return null;
        } 
    }

    /**
     * Sends a request to the ShipperHQ API
     * 
     * @param WebServiceRequestInterface $request The request to send
     * @param string|null $url The URL to send the request to
     * @return array|null The response from the API
     */
    private function sendRequest($request, ?string $url = null): ?array
    {
        $timeout = 30;
        $initVal = microtime(true);
        
        $result = $this->client->sendAndReceive(
            $request, 
            $url ?? $this->config->getRatesUrl(),
            $timeout
        );
        
        $elapsed = microtime(true) - $initVal;
        $this->logger->debug('ShipperHQ API request time: ' . $elapsed);

        // Keep these split into 3 messages, makes it much easier to read in the logs
        $this->logger->info('ShipperHQ Request', ['request' => $request]);
        $this->logger->info('ShipperHQ Response', ['response' => $result['result']]);
        $this->logger->debug('ShipperHQ Debug Response', ['response' => $result['debug']]);

        return $result;
    }
}
