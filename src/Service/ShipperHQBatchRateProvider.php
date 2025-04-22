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
    public function __construct(
        private SystemConfigService $systemConfig,
        private LoggerInterface $logger,
        private ShipperHQClient $apiClient,
        private EntityRepository $shippingMethodRepository,
        private Mapper $mapper
    ) {}

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
                $customFields = $method->getCustomFields() ?? [];
                return [
                    'id' => $method->getId(),
                    'name' => $method->getName(),
                    'technical_name' => $method->getTechnicalName(),
                    'active' => $method->getActive(),
                    'custom_fields' => $customFields
                ];
            }, $shippingMethods)
        ]);
        
        // Filter to only include ShipperHQ methods by checking custom fields
        $shipperHQMethods = array_filter($shippingMethods, function ($method) {
            $customFields = $method->getCustomFields() ?? [];
            return isset($customFields['shipperhq_carrier_code']) && 
                   isset($customFields['shipperhq_method_code']);
        });
        
        $this->logger->info('SHIPPERHQ: Filtered ShipperHQ methods', [
            'total_shipperhq_methods' => count($shipperHQMethods),
            'shipperhq_methods' => array_map(function($method) {
                $customFields = $method->getCustomFields() ?? [];
                return [
                    'id' => $method->getId(),
                    'name' => $method->getName(),
                    'technical_name' => $method->getTechnicalName(),
                    'active' => $method->getActive(),
                    'carrier_code' => $customFields['shipperhq_carrier_code'] ?? '',
                    'method_code' => $customFields['shipperhq_method_code'] ?? ''
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
     * Process the rates response from the API
     *
     * @param \ShipperHQ\WS\Rate\Response\RateResponse $response
     * @param array $shippingMethods
     * @return array
     */
    private function processRatesResponse(\ShipperHQ\WS\Rate\Response\RateResponse $response, array $shippingMethods): array
    {
        $rates = [];
        
        // Check for errors in the response
        if ($response->getErrors() && count($response->getErrors()) > 0) {
            foreach ($response->getErrors() as $error) {
                $this->logger->error('SHIPPERHQ: API Error', [
                    'code' => $error->getErrorCode(),
                    'message' => $error->getInternalErrorMessage(),
                    'external_message' => $error->getExternalErrorMessage()
                ]);
            }
            return $rates;
        }
        
        // Process carrier group responses
        $carrierGroupResponses = $response->getCarrierGroupResponses();
        if (!$carrierGroupResponses) {
            $this->logger->warning('SHIPPERHQ: No carrier group responses found');
            return $rates;
        }
        
        $this->logger->debug('SHIPPERHQ: Processing carrier group responses', [
            'count' => count($carrierGroupResponses)
        ]);
        
        foreach ($carrierGroupResponses as $index => $carrierGroupResponse) {
            $this->logger->debug('SHIPPERHQ: Processing carrier group response', [
                'index' => $index,
                'response' => json_encode($carrierGroupResponse)
            ]);
            
            $carrierRates = $carrierGroupResponse->getCarrierRates();
            if (!$carrierRates) {
                $this->logger->warning('SHIPPERHQ: No carrier rates found in carrier group response', [
                    'index' => $index
                ]);
                continue;
            }
            
            $this->logger->debug('SHIPPERHQ: Processing carrier rates', [
                'count' => count($carrierRates)
            ]);

            foreach ($carrierRates as $carrierRate) {
                // Get the rates array directly from the CarrierRatesResponse object
                $shippingRates = $carrierRate->getRates();
                if (!$shippingRates || empty($shippingRates)) {
                    $this->logger->warning('SHIPPERHQ: No shipping rates found for carrier', [
                        'carrier_code' => $carrierRate->getCarrierCode(),
                        'carrier_title' => $carrierRate->getCarrierTitle()
                    ]);
                    continue;
                }
                
                $this->logger->debug('SHIPPERHQ: Processing shipping rates', [
                    'carrier_code' => $carrierRate->getCarrierCode(),
                    'count' => count($shippingRates)
                ]);

                // Process each shipping rate
                foreach ($shippingRates as $rate) {
                    if (!$rate->getMethodCode() || !$rate->getTotalPrice()) {
                        $this->logger->warning('SHIPPERHQ: Rate missing required fields', [
                            'rate' => json_encode($rate)
                        ]);
                        continue;
                    }

                    // Map ShipperHQ carrier/method to Shopware shipping method ID
                    $methodId = $this->mapCarrierMethodToShopwareId($rate, $shippingMethods);
                    if (!$methodId) {
                        $this->logger->warning('SHIPPERHQ: Could not map carrier/method to Shopware shipping method', [
                            'carrier_code' => $rate->getCarrierCode(),
                            'method_code' => $rate->getMethodCode()
                        ]);
                        continue;
                    }

                    // Add the rate to the result array
                    $rates[$methodId] = [
                        'price' => $rate->getTotalPrice(),
                        'currency' => $rate->getCurrencyCode() ?: 'USD'
                    ];
                    
                    $this->logger->debug('SHIPPERHQ: Added rate to result', [
                        'method_id' => $methodId,
                        'price' => $rate->getTotalPrice(),
                        'currency' => $rate->getCurrencyCode() ?: 'USD'
                    ]);
                }
            }
        }
        
        return $rates;
    }

    /**
     * Map a ShipperHQ carrier/method to a Shopware shipping method ID
     *
     * @param \ShipperHQ\WS\Rate\Response\ShippingRate $rate
     * @param array $shippingMethods
     * @return string|null
     */
    private function mapCarrierMethodToShopwareId(\ShipperHQ\WS\Rate\Response\ShippingRate $rate, array $shippingMethods): ?string
    {
        $carrierCode = $rate->getCarrierCode();
        $methodCode = $rate->getMethodCode();
        
        // Log the mapping attempt
        $this->logger->debug('SHIPPERHQ: Mapping carrier/method to shipping method', [
            'carrier_code' => $carrierCode,
            'method_code' => $methodCode
        ]);
        
        // Look for a matching shipping method
        foreach ($shippingMethods as $method) {
            $customFields = $method->getCustomFields() ?? [];
            $methodCarrierCode = $customFields['shipperhq_carrier_code'] ?? '';
            $methodMethodCode = $customFields['shipperhq_method_code'] ?? '';
            
            if ($methodCarrierCode === $carrierCode && $methodMethodCode === $methodCode) {
                return $method->getId();
            }
        }
        
        // If no exact match, try a more flexible approach
        foreach ($shippingMethods as $method) {
            $customFields = $method->getCustomFields() ?? [];
            $methodCarrierCode = $customFields['shipperhq_carrier_code'] ?? '';
            
            // If we have a carrier code match but no method code, use the first method
            if ($methodCarrierCode === $carrierCode && empty($methodMethodCode)) {
                return $method->getId();
            }
        }
        
        return null;
    }
} 