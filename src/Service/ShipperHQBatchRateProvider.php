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
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SHQ\RateProvider\Helper\Mapper;

class ShipperHQBatchRateProvider
{
    private SystemConfigService $systemConfig;
    private LoggerInterface $logger;
    private ShipperHQApiClient $apiClient;
    private EntityRepository $shippingMethodRepository;
    private Mapper $mapper;

    public function __construct(
        SystemConfigService $systemConfig,
        LoggerInterface $logger,
        ShipperHQApiClient $apiClient,
        EntityRepository $shippingMethodRepository,
        Mapper $mapper
    ) {
        $this->systemConfig = $systemConfig;
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->mapper = $mapper;
    }

    /**
     * Fetch rates for all shipping methods in a single API call
     *
     * @param Cart $cart
     * @param SalesChannelContext $salesChannelContext
     * @return array|null Array of shipping rates or null on error
     */
    public function getBatchRates(Cart $cart, SalesChannelContext $salesChannelContext): ?array
    {
        try {
            $apiKey = $this->systemConfig->get('SHQRateProvider.config.apiKey');
            
            if (!$apiKey) {
                $this->logger->error('ShipperHQ API key not configured');
                return null;
            }

            // Get all ShipperHQ shipping methods
            $shippingMethods = $this->getShipperHQShippingMethods($salesChannelContext);
            if (empty($shippingMethods)) {
                $this->logger->info('No ShipperHQ shipping methods found');
                return [];
            }

            // Build request data for ShipperHQ
            $requestData = $this->buildRequestData($cart, $salesChannelContext, $shippingMethods);
            
            // Check if request data is null (invalid address)
            if ($requestData === null) {
                $this->logger->info('Invalid shipping address, cannot get rates');
                return [];
            }
            
            // Make the API call to ShipperHQ
            $response = $this->apiClient->getRatesForAllMethods($requestData);
            
            $this->logger->info('SHIPPERHQ: API response', [
                'response' => $response
            ]);
            
            if (!$response) {
                $this->logger->error('Invalid response from ShipperHQ API', [
                    'response' => $response
                ]);
                return [];
            }
            
            // Process and return the rates
            return $this->processRatesResponse($response, $shippingMethods);

        } catch (\Exception $e) {
            $this->logger->error('Error fetching batch shipping rates: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Build the request data for ShipperHQ API
     *
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @param array $shippingMethods
     * @return \ShipperHQ\WS\Rate\Request\RateRequest|null
     */
    private function buildRequestData(Cart $cart, SalesChannelContext $context, array $shippingMethods): ?\ShipperHQ\WS\Rate\Request\RateRequest
    {
        if (!$this->is_valid_address($context)) {
            return null;
        }

        // Use the mapper to create a properly formatted request
        $request = $this->mapper->createRequest($cart, $context);
        
        // TODO Do we need this?
    //    $requestArray = json_decode(json_encode($request), true);

        
        return $request;
    }

    /**
     * Get all shipping methods that are managed by ShipperHQ
     *
     * @param SalesChannelContext $context
     * @return array
     */
    private function getShipperHQShippingMethods(SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('prices');
        $criteria->addAssociation('deliveryTime');
        $criteria->addAssociation('tax');
        
        $shippingMethods = $this->shippingMethodRepository->search($criteria, $context->getContext())->getElements();
        
        $this->logger->info('SHIPPERHQ: Found all shipping methods', [
            'total_methods' => count($shippingMethods),
            'methods' => array_map(function($method) {
                return [
                    'id' => $method->getId(),
                    'name' => $method->getName(),
                    'technical_name' => $method->getTechnicalName(),
                    'active' => $method->getActive()
                ];
            }, $shippingMethods)
        ]);
        
        // Filter to only include ShipperHQ methods
        $shipperHQMethods = array_filter($shippingMethods, function ($method) {
            return str_starts_with($method->getTechnicalName(), 'shq');
        });
        
        $this->logger->info('SHIPPERHQ: Filtered ShipperHQ methods', [
            'total_shipperhq_methods' => count($shipperHQMethods),
            'shipperhq_methods' => array_map(function($method) {
                return [
                    'id' => $method->getId(),
                    'name' => $method->getName(),
                    'technical_name' => $method->getTechnicalName(),
                    'active' => $method->getActive()
                ];
            }, $shipperHQMethods)
        ]);
        
        return $shipperHQMethods;
    }

    /**
     * Check if the address is valid for shipping rate calculation
     *
     * @param SalesChannelContext $context The sales channel context containing customer and address information
     * @return bool Whether the address is valid
     */
    private function is_valid_address(SalesChannelContext $context): bool
    {
        $valid_address = true;
        $reason = '';

        $customer = $context->getCustomer();
        $shippingAddress = $customer ? $customer->getActiveShippingAddress() : null;
        
        $destinationCountry = $shippingAddress && $shippingAddress->getCountry() ? $shippingAddress->getCountry()->getIso() : '';
        $destinationState = $shippingAddress && $shippingAddress->getCountryState() ? $shippingAddress->getCountryState()->getShortCode() : '';
        $destinationPostcode = $shippingAddress ? $shippingAddress->getZipcode() : '';

        $this->logger->info('SHIPPERHQ: Validating shipping address', [
            'has_customer' => $customer !== null,
            'has_shipping_address' => $shippingAddress !== null,
            'country' => $destinationCountry,
            'state' => $destinationState,
            'postcode' => $destinationPostcode
        ]);

        // Always return true to allow API calls even without an address
        // This will let ShipperHQ handle the validation on their end
        return true;
    }

    /**
     * Process the rates response from ShipperHQ
     *
     * @param array $ratesResponse
     * @param array $shippingMethods
     * @return array
     */
    private function processRatesResponse($ratesResponse, array $shippingMethods): array
    {
        $processedRates = [];
        
        // Convert to array if it's an object
        if (is_object($ratesResponse)) {
            $this->logger->info('SHIPPERHQ: Converting object response to array');
            $ratesResponse = json_decode(json_encode($ratesResponse), true);
        }
        
        $this->logger->info('SHIPPERHQ: Processing rates response', [
            'response_type' => gettype($ratesResponse),
            'is_array' => is_array($ratesResponse),
            'response' => $ratesResponse
        ]);
        
        if (!is_array($ratesResponse)) {
            $this->logger->error('SHIPPERHQ: Invalid rates response format', [
                'type' => gettype($ratesResponse)
            ]);
            return [];
        }

        // Process merged rates if available
        if (isset($ratesResponse['mergedRateResponse']['shippingRates'])) {
            foreach ($ratesResponse['mergedRateResponse']['shippingRates'] as $rate) {
                if (!isset($rate['methodCode']) || !isset($rate['totalPrice'])) {
                    $this->logger->warning('SHIPPERHQ: Rate missing required fields', [
                        'rate' => $rate
                    ]);
                    continue;
                }
                
                // Map ShipperHQ carrier/method to Shopware shipping method ID
                $methodId = $this->mapCarrierMethodToShopwareId($rate, $shippingMethods);
                
                if (!$methodId) {
                    $this->logger->warning('SHIPPERHQ: Could not map rate to shipping method', [
                        'rate' => $rate
                    ]);
                    continue;
                }

                // Calculate tax
                $taxRate = 0.0; // Default tax rate
                if (isset($rate['taxAmount']) && $rate['totalPrice'] > 0) {
                    $taxRate = ($rate['taxAmount'] / $rate['totalPrice']) * 100;
                }
                
                $processedRates[$methodId] = [
                    'methodCode' => $methodId,
                    'gross' => (float) $rate['totalPrice'],
                    'net' => (float) ($rate['totalPrice'] - ($rate['taxAmount'] ?? 0)),
                    'tax_rate' => $taxRate,
                    'carrierCode' => $rate['carrierCode'] ?? '',
                    'carrierTitle' => $rate['carrierTitle'] ?? '',
                    'methodTitle' => $rate['methodTitle'] ?? '',
                    'transitTime' => $rate['transitTime'] ?? null,
                    'deliveryDate' => $rate['deliveryDate'] ?? null
                ];
            }
        }

        // Process carrier group responses if no merged rates
        if (empty($processedRates) && isset($ratesResponse['carrierGroupResponses'])) {
            foreach ($ratesResponse['carrierGroupResponses'] as $groupResponse) {
                if (!isset($groupResponse['carrierRates'])) {
                    continue;
                }

                foreach ($groupResponse['carrierRates'] as $carrierRate) {
                    if (!isset($carrierRate['rates'])) {
                        continue;
                    }

                    foreach ($carrierRate['rates'] as $rate) {
                        if (!isset($rate['methodCode']) || !isset($rate['totalPrice'])) {
                            continue;
                        }

                        // Map ShipperHQ carrier/method to Shopware shipping method ID
                        $methodId = $this->mapCarrierMethodToShopwareId($rate, $shippingMethods);
                
                        if (!$methodId) {
                            continue;
                        }

                        // Calculate tax
                        $taxRate = 0.0; // Default tax rate
                        if (isset($rate['taxAmount']) && $rate['totalPrice'] > 0) {
                            $taxRate = ($rate['taxAmount'] / $rate['totalPrice']) * 100;
                        }

                        $processedRates[$methodId] = [
                            'methodCode' => $methodId,
                            'gross' => (float) $rate['totalPrice'],
                            'net' => (float) ($rate['totalPrice'] - ($rate['taxAmount'] ?? 0)),
                            'tax_rate' => $taxRate,
                            'carrierCode' => $carrierRate['carrierCode'] ?? '',
                            'carrierTitle' => $carrierRate['carrierTitle'] ?? '',
                            'methodTitle' => $rate['methodTitle'] ?? '',
                            'transitTime' => $rate['transitTime'] ?? null,
                            'deliveryDate' => $rate['deliveryDate'] ?? null
                        ];
                    }
                }
            }
        }
        
        $this->logger->info('SHIPPERHQ: Processed rates', [
            'processed_count' => count($processedRates),
            'processed_rates' => $processedRates
        ]);
        
        return $processedRates;
    }

    /**
     * Map ShipperHQ carrier/method to Shopware shipping method ID
     *
     * @param array $rate
     * @param array $shippingMethods
     * @return string|null
     */
    private function mapCarrierMethodToShopwareId(array $rate, array $shippingMethods): ?string
    {
        $carrierCode = $rate['carrierCode'] ?? '';
        $methodCode = $rate['methodCode'] ?? '';
        
        $this->logger->info('SHIPPERHQ: Mapping carrier method to Shopware ID', [
            'carrier_code' => $carrierCode,
            'method_code' => $methodCode
        ]);
        
        // This mapping logic will depend on how you've set up your shipping methods
        // For simplicity, we're assuming the ShipperHQ carrier+method code matches
        // the technical name of the Shopware shipping method
        
        $shipperhqCode = "shq{$carrierCode}-{$methodCode}";
        
        $this->logger->info('SHIPPERHQ: Looking for shipping method with technical name', [
            'shipperhq_code' => $shipperhqCode
        ]);
        
        foreach ($shippingMethods as $method) {
            $technicalName = $method->getTechnicalName();
            $this->logger->debug('SHIPPERHQ: Checking shipping method', [
                'id' => $method->getId(),
                'name' => $method->getName(),
                'technical_name' => $technicalName
            ]);
            
            if ($technicalName === $shipperhqCode) {
                $this->logger->info('SHIPPERHQ: Found exact match for shipping method', [
                    'id' => $method->getId(),
                    'name' => $method->getName(),
                    'technical_name' => $technicalName
                ]);
                return $method->getId();
            }
        }
        
        // If no direct match, try matching just by carrier
        $carrierPrefix = "shq{$carrierCode}";
        $this->logger->info('SHIPPERHQ: No exact match, trying carrier prefix', [
            'carrier_prefix' => $carrierPrefix
        ]);
        
        foreach ($shippingMethods as $method) {
            $technicalName = $method->getTechnicalName();
            if (strpos($technicalName, $carrierPrefix) === 0) {
                $this->logger->info('SHIPPERHQ: Found carrier match for shipping method', [
                    'id' => $method->getId(),
                    'name' => $method->getName(),
                    'technical_name' => $technicalName
                ]);
                return $method->getId();
            }
        }
        
        $this->logger->warning('SHIPPERHQ: No matching shipping method found', [
            'carrier_code' => $carrierCode,
            'method_code' => $methodCode
        ]);
        
        return null;
    }
} 