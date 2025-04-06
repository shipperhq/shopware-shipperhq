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

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use ShipperHQ\WS\Rate\Request\RateRequest;
use ShipperHQ\WS\Shared\Credentials;
use ShipperHQ\WS\Shared\SiteDetails;
use ShipperHQ\WS\Shared\Address;
use ShipperHQ\WS\Rate\Request\Checkout\Cart as ShipperHQCart;
use ShipperHQ\WS\Rate\Request\Checkout\Item;
use ShipperHQ\WS\Rate\Request\CustomerDetails;

class Mapper
{
    /**
     * Custom Product Attributes
     * @var array
     */
    protected static array $customAttributeNames = [
        'shipperhq_shipping_group', 'freight_class', 'ship_separately',
        'shipperhq_dim_group', 'must_ship_freight', 'shipperhq_warehouse', 'shipperhq_hs_code'
    ];

    /**
     * Standard attributes
     * @var array
     */
    protected static array $stdAttributeNames = [
        'height', 'width', 'length'
    ];

    protected static string $origin = 'shipperhq_warehouse';
    protected static string $location = 'shipperhq_location';

    private SystemConfigService $systemConfig;
    private LoggerInterface $logger;
    private EntityRepository $productRepository;

    public function __construct(
        SystemConfigService $systemConfig,
        LoggerInterface $logger,
        EntityRepository $productRepository
    ) {
        $this->systemConfig = $systemConfig;
        $this->logger = $logger;
        $this->productRepository = $productRepository;
    }

    /**
     * Create a ShipperHQ rate request from Shopware cart data
     * 
     * Note: Already have credentials, site details, etc in the request object so not redoing here
     *
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return RateRequest|null
     */
    public function createRequest(Cart $cart, SalesChannelContext $context): ?RateRequest
    {
        // if (!$this->hasCredentialsEntered()) {
        //     $this->logger->error('ShipperHQ API credentials not configured');
        //     return null;
        // }

        $shipperHQRequest = new RateRequest();
        $shipperHQRequest->cart = $this->getCartDetails($cart);
        $shipperHQRequest->destination = $this->getDestination($context);
        $shipperHQRequest->customerDetails = $this->getCustomerGroupDetails($context);
        $shipperHQRequest->cartType = $this->getCartType();

        // NOTE: Does this inside the api client call on shopware - could move here if required
       // $shipperHQRequest->siteDetails = $this->getSiteDetails();
       // $shipperHQRequest->credentials = $this->getCredentials();

        return $shipperHQRequest;
    }

    /**
     * Return credentials for ShipperHQ login
     * @return Credentials
     */
    public function getCredentials(): Credentials
    {
        $credentials = new Credentials();
        $credentials->apiKey = $this->systemConfig->get('SHQRateProvider.config.apiKey');
        $credentials->password = $this->systemConfig->get('SHQRateProvider.config.password', '');
        
        return $credentials;
    }

    /**
     * Check if credentials are entered
     *
     * @return bool
     */
    private function hasCredentialsEntered(): bool
    {
        $apiKey = $this->systemConfig->get('SHQRateProvider.config.apiKey');
        
        if (!empty($apiKey)) {
            return true;
        }

        return false;
    }

    /**
     * Format cart for ShipperHQ
     *
     * @param Cart $cart
     * @return ShipperHQCart
     */
    public function getCartDetails(Cart $cart): ShipperHQCart
    {
        $cartDetails = new ShipperHQCart();
        $cartDetails->declaredValue = $cart->getPrice()->getTotalPrice();
        $cartDetails->items = $this->getFormattedItems($cart->getLineItems()->getElements());
        
        return $cartDetails;
    }

    /**
     * Return site specific information
     * @return SiteDetails
     */
    public function getSiteDetails(): SiteDetails
    {
        $siteDetails = new SiteDetails();
        $siteDetails->ecommerceCart = "Shopware";
        $siteDetails->ecommerceVersion = $this->getShopwareVersion();
        $siteDetails->websiteUrl = $this->systemConfig->get('core.basicInformation.shopUrl');
        $siteDetails->environmentScope = "LIVE";
        $siteDetails->appVersion = $this->getPluginVersion();
        
        return $siteDetails;
    }

    /**
     * Get Shopware version
     *
     * @return string
     */
    private function getShopwareVersion(): string
    {
        return defined('Shopware\Core\Framework\Adapter\Kernel\KernelFactory::SHOPWARE_VERSION') 
            ? \Shopware\Core\Framework\Adapter\Kernel\KernelFactory::SHOPWARE_VERSION 
            : '6.0.0';
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    private function getPluginVersion(): string
    {
        // You may want to store this in your plugin configuration
        return $this->systemConfig->get('SHQRateProvider.config.version', '1.0.0');
    }

    /**
     * Get formatted items for ShipperHQ
     *
     * @param array $items
     * @param bool $useChild
     * @return array
     */
    private function getFormattedItems(array $items, bool $useChild = false): array
    {
        $formattedItems = [];
        $counter = 0;

        foreach ($items as $item) {
            if (!$item instanceof LineItem || $item->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $counter++;
            $productId = $item->getReferencedId();
            $product = $this->getProduct($productId);
            
            if (!$product) {
                continue;
            }

            $warehouseDetails = $this->getWarehouseDetails($product);
            $pickupLocationDetails = $this->getPickupLocationDetails($product);

            // Get product data
            $sku = $product->getProductNumber();
            $id = $counter;
            $productType = "simple"; // Shopware doesn't have the same concept of configurable products as Magento

            $weight = $product->getWeight() ?? 0;
            $qty = $item->getQuantity();
            $itemPrice = $item->getPrice() ? $item->getPrice()->getUnitPrice() : 0;
            $discountedPrice = $itemPrice; // For now, we're not handling discounts separately
            $currency = "USD"; // TODO Default, should be replaced with actual currency

            $formattedItem = new Item();
            $formattedItem->id = $id;
            $formattedItem->sku = $sku;
            $formattedItem->storePrice = $itemPrice;
            $formattedItem->weight = $weight;
            $formattedItem->qty = $qty;
            $formattedItem->type = $productType;
            $formattedItem->items = []; // child items
            $formattedItem->basePrice = $itemPrice;
            $formattedItem->taxInclBasePrice = $itemPrice;
            $formattedItem->taxInclStorePrice = $itemPrice;
            $formattedItem->rowTotal = $itemPrice * $qty;
            $formattedItem->baseRowTotal = $itemPrice * $qty;
            $formattedItem->discountPercent = 0; // TODO: Handle discounts
            $formattedItem->discountedBasePrice = $discountedPrice;
            $formattedItem->discountedStorePrice = $discountedPrice;
            $formattedItem->discountedTaxInclBasePrice = $discountedPrice;
            $formattedItem->discountedTaxInclStorePrice = $discountedPrice;
            $formattedItem->attributes = $this->populateAttributes($product);
            $formattedItem->baseCurrency = $currency;
            $formattedItem->packageCurrency = $currency;
            $formattedItem->storeBaseCurrency = $currency;
            $formattedItem->storeCurrentCurrency = $currency;
            $formattedItem->taxPercentage = 0.00; // TODO: Get actual tax percentage
            $formattedItem->freeShipping = false; // TODO: Check if free shipping
            $formattedItem->fixedPrice = false;
            $formattedItem->fixedWeight = false;
            $formattedItem->warehouseDetails = $warehouseDetails;
            $formattedItem->pickupLocationDetails = $pickupLocationDetails;

            $formattedItems[] = $formattedItem;
        }

        return $formattedItems;
    }

    /**
     * Get product entity by ID
     *
     * @param string $productId
     * @return \Shopware\Core\Content\Product\ProductEntity|null
     */
    private function getProduct(string $productId): ?\Shopware\Core\Content\Product\ProductEntity
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('customFields');
        
        return $this->productRepository->search($criteria, \Shopware\Core\Framework\Context::createDefaultContext())->first();
    }

    /**
     * Get destination address
     *
     * @param SalesChannelContext $context
     * @return Address
     */
    private function getDestination(SalesChannelContext $context): Address
    {
        $destination = new Address();
        $customer = $context->getCustomer();
        $shippingAddress = $customer ? $customer->getActiveShippingAddress() : null;

        if ($shippingAddress) {
            $destination->city = $shippingAddress->getCity();
            $destination->country = $shippingAddress->getCountry() ? $shippingAddress->getCountry()->getIso() : '';
            $destination->region = $shippingAddress->getCountryState() ? $shippingAddress->getCountryState()->getShortCode() : '';
            $destination->street = $shippingAddress->getStreet();
            $destination->zipcode = $shippingAddress->getZipcode();
        }

        return $destination;
    }

    /**
     * Populate product attributes for ShipperHQ
     *
     * @param \Shopware\Core\Content\Product\ProductEntity $product
     * @return array
     */
    protected function populateAttributes(\Shopware\Core\Content\Product\ProductEntity $product): array
    {
        $attributes = [];
        
        // Add standard attributes
        foreach (self::$stdAttributeNames as $attributeName) {
            $value = $this->getProductAttribute($product, $attributeName);
            
            if ($value !== null) {
                $attributes[] = [
                    'name' => $attributeName,
                    'value' => $value
                ];
            }
        }
        
        // Add custom attributes
        foreach (self::$customAttributeNames as $attributeName) {
            $value = $this->getProductAttribute($product, $attributeName);
            
            if ($value !== null) {
                // Convert boolean values for certain attributes
                if (in_array(strtolower($attributeName), ['ship_separately', 'must_ship_freight']) && is_string($value)) {
                    $value = strtolower($value) === 'yes' || $value === '1' || $value === 'true';
                }
                
                $attributes[] = [
                    'name' => $attributeName,
                    'value' => $value
                ];
            }
        }
        
        return $attributes;
    }

    /**
     * Get product attribute value
     *
     * @param \Shopware\Core\Content\Product\ProductEntity $product
     * @param string $attributeName
     * @return mixed|null
     */
    private function getProductAttribute(\Shopware\Core\Content\Product\ProductEntity $product, string $attributeName)
    {
        // First check if it's a standard product property
        $getterMethod = 'get' . ucfirst($attributeName);
        if (method_exists($product, $getterMethod)) {
            return $product->$getterMethod();
        }
        
        // Then check custom fields
        $customFields = $product->getCustomFields();
        if ($customFields && isset($customFields[$attributeName])) {
            return $customFields[$attributeName];
        }
        
        return null;
    }

    /**
     * Get warehouse details
     *
     * @param \Shopware\Core\Content\Product\ProductEntity $product
     * @return array|null
     */
    public function getWarehouseDetails(\Shopware\Core\Content\Product\ProductEntity $product): ?array
    {
        // Implement warehouse details logic if needed
        return null;
    }

    /**
     * Get pickup location details
     *
     * @param \Shopware\Core\Content\Product\ProductEntity $product
     * @return array|null
     */
    public function getPickupLocationDetails(\Shopware\Core\Content\Product\ProductEntity $product): ?array
    {
        // Implement pickup location details logic if needed
        return null;
    }

    /**
     * Get customer group details
     *
     * @param SalesChannelContext $context
     * @return CustomerDetails
     */
    public function getCustomerGroupDetails(SalesChannelContext $context): CustomerDetails
    {
        $customerGroup = new CustomerDetails();
        $customer = $context->getCustomer();
        
        if ($customer) {
            $customerGroup->customerGroup = $context->getCurrentCustomerGroup()->getName();
            $customerGroup->customerGroupId = $context->getCurrentCustomerGroup()->getId();
        }
        
        return $customerGroup;
    }

    /**
     * Get cart type
     *
     * @return string
     */
    public function getCartType(): string
    {
        return "checkout";
    }

    public function mapResponse($result): array
    {
        // Log the initial result
        // $this->logger->debug('SHIPPERHQ: Initial response structure', [
        //     'result' => $result,
        //     'type' => gettype($result)
        // ]);

        // Convert stdClass to array if needed
        if (is_object($result)) {
            $result = json_decode(json_encode($result), true);
            // $this->logger->debug('SHIPPERHQ: Converted object to array', [
            //     'result' => $result
            // ]);
        }

        // Handle the nested response structure
        if (isset($result['response']['result']['stdClass'])) {
         //   $this->logger->debug('SHIPPERHQ: Found response.result.stdClass path');
            $result = $result['response']['result']['stdClass'];
        } elseif (isset($result['result']['stdClass'])) {
          //  $this->logger->debug('SHIPPERHQ: Found result.stdClass path');
            $result = $result['result']['stdClass'];
        } elseif (isset($result['stdClass'])) {
         //   $this->logger->debug('SHIPPERHQ: Found stdClass path');
            $result = $result['stdClass'];
        }

        // $this->logger->debug('SHIPPERHQ: Final result structure before mapping', [
        //     'result' => $result,
        //     'keys' => array_keys($result)
        // ]);

        $rateResponse = new \ShipperHQ\WS\Rate\Response\RateResponse();

        // Map errors if present
        if (isset($result['errors']) && !empty($result['errors'])) {
            // $this->logger->debug('SHIPPERHQ: Processing errors', [
            //     'errors' => $result['errors']
            // ]);
            $errors = array_map(function ($error) {
                // Convert error object to array if needed
                if (is_object($error)) {
                    $error = json_decode(json_encode($error), true);
                }
                $webServiceError = new \ShipperHQ\WS\Rate\Response\WebServiceError(
                    $error['errorCode'] ?? null,
                    $error['internalErrorMessage'] ?? null,
                    $error['externalErrorMessage'] ?? null
                );
                if (isset($error['priority'])) {
                    $webServiceError->setPriority($error['priority']);
                }
                return $webServiceError;
            }, $result['errors']);
            $rateResponse->setErrors($errors);
        }

        // Map response summary
        if (isset($result['responseSummary'])) {
            // $this->logger->debug('SHIPPERHQ: Processing response summary', [
            //     'summary' => $result['responseSummary']
            // ]);
            $summaryData = is_object($result['responseSummary']) 
                ? json_decode(json_encode($result['responseSummary']), true) 
                : $result['responseSummary'];
            
            $summary = new \ShipperHQ\WS\Rate\Response\ResponseSummary(
                $summaryData['date'] ?? 0,
                $summaryData['status'] ?? -1,
                $summaryData['version'] ?? ''
            );
            $summary->setTransactionId($summaryData['transactionId'] ?? '');
            $summary->setProfileId($summaryData['profileId'] !== null ? (string)$summaryData['profileId'] : '');
            $summary->setProfileName($summaryData['profileName'] ?? '');
            $summary->setCacheStatus($summaryData['cacheStatus'] ?? '');
            $summary->setExperimentName($summaryData['experimentName'] ?? '');
            $rateResponse->setResponseSummary($summary);
        }

        // Map global settings
        if (isset($result['globalSettings'])) {
            // $this->logger->debug('SHIPPERHQ: Processing global settings', [
            //     'settings' => $result['globalSettings']
            // ]);
            $settingsData = is_object($result['globalSettings']) 
                ? json_decode(json_encode($result['globalSettings']), true) 
                : $result['globalSettings'];
            
            $settings = new \ShipperHQ\WS\Rate\Response\GlobalSettings(
                $settingsData,
                $settingsData['cityRequired'] ?? false
            );
            $rateResponse->setGlobalSettings($settings);
        }

        // Map merged rate response
        if (isset($result['mergedRateResponse'])) {
            // $this->logger->debug('SHIPPERHQ: Processing merged rate response', [
            //     'merged' => $result['mergedRateResponse']
            // ]);
            $mergedData = is_object($result['mergedRateResponse']) 
                ? json_decode(json_encode($result['mergedRateResponse']), true) 
                : $result['mergedRateResponse'];
            
            $mergedResponse = new \ShipperHQ\WS\Rate\Response\Merge\MergedRateResponse();
            $mergedRates = array_map(function ($rate) {
                // Convert rate object to array if needed
                if (is_object($rate)) {
                    $rate = json_decode(json_encode($rate), true);
                }
                return new \ShipperHQ\WS\Rate\Response\ShippingRate(
                    $rate['carrierCode'] ?? '',
                    $rate['methodCode'] ?? '',
                    $rate['methodTitle'] ?? '',
                    $rate['totalPrice'] ?? 0.0,
                    $rate['currencyCode'] ?? '',
                    $rate['attributes'] ?? []
                );
            }, $mergedData['shippingRates'] ?? []);
            $mergedResponse->setShippingRates($mergedRates);
            $rateResponse->setMergedRateResponse($mergedResponse);
        }

        // Map carrier group responses
        if (isset($result['carrierGroups'])) {
            // $this->logger->debug('SHIPPERHQ: Processing carrier groups', [
            //     'groups' => $result['carrierGroups']
            // ]);
            $carrierGroupResponses = array_map(function ($groupResponse) {
                // Convert group response object to array if needed
                if (is_object($groupResponse)) {
                    $groupResponse = json_decode(json_encode($groupResponse), true);
                }
                $response = new \ShipperHQ\WS\Rate\Response\Shipping\CarrierGroupResponse();
                
                // Set carrier group detail
                $carrierGroupId = $groupResponse['carrierGroupId'] ?? '';
                $carrierGroupName = $groupResponse['carrierGroupName'] ?? '';
                $carrierGroupDetail = new \ShipperHQ\WS\Rate\Response\Shipping\Carrier\CarrierGroupDetail(
                    $carrierGroupId,
                    $carrierGroupName
                );
                $response->setCarrierGroupDetail($carrierGroupDetail);
                
                // Set carrier rates
                $carrierRates = $groupResponse['carrierRates'] ?? [];
                if (is_object($carrierRates)) {
                    $carrierRates = json_decode(json_encode($carrierRates), true);
                }
                if (!is_array($carrierRates)) {
                    $carrierRates = [];
                }
                
                // Convert carrier rates to CarrierRatesResponse objects
                $carrierRatesResponses = [];
                foreach ($carrierRates as $rate) {
                    $carrierRateResponse = new \ShipperHQ\WS\Rate\Response\Shipping\CarrierRatesResponse();
                    $carrierRateResponse->setCarrierCode($rate['carrierCode'] ?? '');
                    $carrierRateResponse->setCarrierTitle($rate['carrierTitle'] ?? '');
                    
                    // Set shipping rates
                    $shippingRates = $rate['shippingRates'] ?? [];
                    if (is_object($shippingRates)) {
                        $shippingRates = json_decode(json_encode($shippingRates), true);
                    }
                    if (!is_array($shippingRates)) {
                        $shippingRates = [];
                    }
                    
                    // Convert shipping rates to ShippingRate objects
                    $shippingRateObjects = [];
                    foreach ($shippingRates as $shippingRate) {
                        $shippingRateObject = new \ShipperHQ\WS\Rate\Response\ShippingRate();
                        $shippingRateObject->setCarrierCode($shippingRate['carrierCode'] ?? '');
                        $shippingRateObject->setMethodCode($shippingRate['methodCode'] ?? '');
                        $shippingRateObject->setMethodTitle($shippingRate['methodTitle'] ?? '');
                        $shippingRateObject->setTotalPrice($shippingRate['totalPrice'] ?? 0.0);
                        $shippingRateObject->setCurrencyCode($shippingRate['currencyCode'] ?? '');
                        $shippingRateObject->setAttributes($shippingRate['attributes'] ?? []);
                        $shippingRateObjects[] = $shippingRateObject;
                    }
                    $carrierRateResponse->setCarrierRates($shippingRateObjects);
                    
                    $carrierRatesResponses[] = $carrierRateResponse;
                }
                $response->setCarrierRates($carrierRatesResponses);
                
                // Set products if available
                $products = $groupResponse['products'] ?? [];
                if (is_object($products)) {
                    $products = json_decode(json_encode($products), true);
                }
                if (!is_array($products)) {
                    $products = [];
                }
                
                // Convert products to ProductInfo objects
                $productInfos = [];
                foreach ($products as $product) {
                    $productInfo = new \ShipperHQ\WS\Rate\Response\ProductInfo();
                    // Set properties on productInfo based on product data
                    // This will depend on the structure of your product data
                    $productInfos[] = $productInfo;
                }
                $response->setProducts($productInfos);
                
                return $response;
            }, $result['carrierGroups']);
            $rateResponse->setCarrierGroupResponses($carrierGroupResponses);
        }

        // Map address validation response if present
        if (isset($result['addressValidationResponse'])) {
            // $this->logger->debug('SHIPPERHQ: Processing address validation', [
            //     'validation' => $result['addressValidationResponse']
            // ]);
            $validationData = is_object($result['addressValidationResponse']) 
                ? json_decode(json_encode($result['addressValidationResponse']), true) 
                : $result['addressValidationResponse'];
            
            $validationResponse = new \ShipperHQ\WS\Rate\Response\AV\AddressValidationResponse();
            $validationResponse->setErrors($validationData['errors'] ?? []);
            $validationResponse->setValid($validationData['valid'] ?? false);
            $rateResponse->setAddressValidationResponse($validationResponse);
        }

        // Check if we have errors in the response
        if (isset($result['errors']) && !empty($result['errors'])) {
            // $this->logger->debug('SHIPPERHQ: Found errors in response', [
            //     'errors' => $result['errors']
            // ]);
            
            // Create a ResponseSummary if not already set
            if (!$rateResponse->getResponseSummary()) {
                $summary = new \ShipperHQ\WS\Rate\Response\ResponseSummary(
                    time() * 1000, // Current timestamp in milliseconds
                    -1, // Error status
                    '1.0' // Default version
                );
                $summary->setTransactionId('ERROR_' . uniqid());
                $rateResponse->setResponseSummary($summary);
            }
        }

        $mappedResponse = $rateResponse->toArray();
        
        // Manually add errors and response summary to the mapped response
        if ($rateResponse->getErrors()) {
            $mappedResponse['errors'] = array_map(function ($error) {
                return [
                    'errorCode' => $error->getErrorCode(),
                    'internalErrorMessage' => $error->getInternalErrorMessage(),
                    'externalErrorMessage' => $error->getExternalErrorMessage(),
                    'priority' => $error->getPriority()
                ];
            }, $rateResponse->getErrors());
        }
        
        if ($rateResponse->getResponseSummary()) {
            $summary = $rateResponse->getResponseSummary();
            $mappedResponse['responseSummary'] = [
                'date' => $summary->getDate(),
                'version' => $summary->getVersion(),
                'transactionId' => $summary->getTransactionId(),
                'status' => $summary->getStatus(),
                'profileId' => $summary->getProfileId(),
                'profileName' => $summary->getProfileName(),
                'cacheStatus' => $summary->getCacheStatus(),
                'experimentName' => $summary->getExperimentName()
            ];
        }

        // $this->logger->debug('SHIPPERHQ: Final mapped response', [
        //     'mapped_response' => $mappedResponse
        // ]);

        return $mappedResponse;
    }
} 