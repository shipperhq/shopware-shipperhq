<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Rating\Decorator;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Shipping\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodPriceEntity;
use SHQ\RateProvider\Service\ShippingRateCache;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;

class DeliveryCalculatorDecoratorold extends DeliveryCalculator
{
    private DeliveryCalculator $decorated;
    private LoggerInterface $logger;
    private ShippingRateCache $rateCache;
    private QuantityPriceCalculator $priceCalculator;
    private EntityRepository $shippingMethodRepository;

    public function __construct(
        QuantityPriceCalculator $priceCalculator,
        LoggerInterface $logger,
        ShippingRateCache $rateCache,
        EntityRepository $shippingMethodRepository
    ) {
        $this->logger = $logger;
        $this->rateCache = $rateCache;
        $this->priceCalculator = $priceCalculator;
        $this->shippingMethodRepository = $shippingMethodRepository;
    }

    public function calculate(CartDataCollection $data, Cart $cart, DeliveryCollection $deliveries, SalesChannelContext $context): void
    {
        $this->logger->info('SHIPPERHQ: Starting delivery calculation', [
            'cart_total' => $cart->getPrice()->getTotalPrice(),
            'cart_quantity' => $cart->getLineItems()->count(),
            'context_currency' => $context->getCurrency()->getIsoCode(),
            'initial_deliveries_count' => $deliveries->count()
        ]);

        // Get all available shipping methods for the current sales channel
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));
        
        $shippingMethods = $this->shippingMethodRepository->search($criteria, $context->getContext())->getEntities();
        
        // Check if we have any ShipperHQ methods
        $hasShipperHQMethods = false;
        foreach ($shippingMethods as $shippingMethod) {
            $customFields = $shippingMethod->getCustomFields() ?? [];
            if (isset($customFields['shipperhq_carrier_code']) && isset($customFields['shipperhq_method_code'])) {
                $hasShipperHQMethods = true;
                break;
            }
        }

        // If we have ShipperHQ methods, we'll handle them ourselves
        if ($hasShipperHQMethods) {
            $this->logger->info('SHIPPERHQ: Found ShipperHQ methods, handling delivery calculation ourselves');
            
            // Clear any existing deliveries
            $deliveries->clear();
            
            // Get all physical line items that need shipping
            $deliveryLineItems = $cart->getLineItems()->filter(function ($lineItem) {
                return $lineItem->getDeliveryInformation() && !$lineItem->getDeliveryInformation()->getFreeDelivery();
            });

            if ($deliveryLineItems->count() === 0) {
                $this->logger->info('SHIPPERHQ: No physical line items found that need shipping');
                return;
            }

            // Convert LineItemCollection to DeliveryPositionCollection
            $deliveryPositions = new DeliveryPositionCollection();
            foreach ($deliveryLineItems as $lineItem) {
                $deliveryPositions->add(new \Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition(
                    $lineItem->getId(),
                    $lineItem,
                    $lineItem->getQuantity(),
                    $lineItem->getPrice(),
                    new \Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate(
                        new \DateTimeImmutable(),
                        new \DateTimeImmutable()
                    )
                ));
            }

            // Get ShipperHQ rates
            $rates = $this->rateCache->getRates($cart, $context);
            $this->logger->info('SHIPPERHQ: Got rates from cache', [
                'rates_count' => count($rates),
                'rates' => $rates
            ]);

            $this->logger->info('SHIPPERHQ: Found shipping methods', [
                'total_methods' => $shippingMethods->count(),
                'method_ids' => array_map(function($method) {
                    return [
                        'id' => $method->getId(),
                        'name' => $method->getName(),
                        'custom_fields' => $method->getCustomFields()
                    ];
                }, $shippingMethods->getElements())
            ]);

            // Process all shipping methods
            foreach ($shippingMethods as $shippingMethod) {
                // Check if this is a ShipperHQ shipping method using custom fields
                $customFields = $shippingMethod->getCustomFields() ?? [];
                $isShipperHQ = isset($customFields['shipperhq_carrier_code']) && 
                               isset($customFields['shipperhq_method_code']);
                
                if (!$isShipperHQ) {
                    continue;
                }

                $this->logger->info('SHIPPERHQ: Processing ShipperHQ method', [
                    'method_id' => $shippingMethod->getId(),
                    'method_name' => $shippingMethod->getName(),
                    'custom_fields' => $customFields
                ]);

                // Get the rate for this shipping method
                $this->logger->info('SHIPPERHQ: Getting rate for method', [
                    'method_id' => $shippingMethod->getId(),
                    'method_name' => $shippingMethod->getName()
                ]);
                
                $rate = $this->rateCache->getRateForMethod($shippingMethod->getId(), $cart, $context);
                
                $this->logger->info('SHIPPERHQ: Rate result for method', [
                    'method_id' => $shippingMethod->getId(),
                    'method_name' => $shippingMethod->getName(),
                    'rate' => $rate
                ]);
                
                if ($rate === null) {
                    $this->logger->warning('SHIPPERHQ: No rate found for shipping method, skipping', [
                        'method_id' => $shippingMethod->getId(),
                        'method_name' => $shippingMethod->getName()
                    ]);
                    continue;
                }

                // Calculate shipping costs with tax rules
                $shippingCosts = $this->calculateShippingCosts($rate, $shippingMethod, $cart, $context);

                $this->logger->info('SHIPPERHQ: Shipping costs calculated', [
                    'shipping_costs' => $shippingCosts->getTotalPrice()
                ]);

                // Create a new delivery for this shipping method
                $delivery = new Delivery(
                    $deliveryPositions,
                    new \Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate(
                        new \DateTimeImmutable(),
                        new \DateTimeImmutable()
                    ),
                    $shippingMethod,
                    $context->getShippingLocation(),
                    $shippingCosts
                );

                // Add the delivery to the collection
                $deliveries->add($delivery);

                $this->logger->info('SHIPPERHQ: Added delivery for method', [
                    'method_id' => $shippingMethod->getId(),
                    'method_name' => $shippingMethod->getName(),
                    'delivery' => [
                        'shipping_costs' => $shippingCosts->getTotalPrice(),
                        'shipping_method' => $shippingMethod->getName(),
                        'positions_count' => count($delivery->getPositions())
                    ]
                ]);

                // Remove any existing errors for this shipping method
                $cart->getErrors()->filter(function ($error) use ($shippingMethod) {
                    $this->logger->info('SHIPPERHQ: Filtering errors', [
                        'error' => $error,
                        'shipping_method' => $shippingMethod->getTranslation('name')
                    ]);
                    return !($error instanceof ShippingMethodBlockedError && 
                            $error->getMessage() === (string) $shippingMethod->getTranslation('name'));
                });
            }

            // If we have ShipperHQ deliveries, skip the core calculator
            if ($deliveries->count() > 0) {
                $this->logger->info('SHIPPERHQ: Skipping core calculator as we have ShipperHQ deliveries', [
                    'deliveries_count' => $deliveries->count(),
                    'delivery_methods' => array_map(function($delivery) {
                        return [
                            'method_id' => $delivery->getShippingMethod()->getId(),
                            'method_name' => $delivery->getShippingMethod()->getName(),
                            'shipping_costs' => $delivery->getShippingCosts()->getTotalPrice()
                        ];
                    }, $deliveries->getElements())
                ]);

                // Log the cart errors to see if there are any shipping-related errors
                $this->logger->info('SHIPPERHQ: Cart errors after processing', [
                    'errors' => array_map(function($error) {
                        return [
                            'type' => get_class($error),
                            'message' => $error->getMessage()
                        ];
                    }, $cart->getErrors()->getElements())
                ]);

                return;
            } else {
                // If we don't have any ShipperHQ methods, let the core handle the calculation
                $this->logger->info('SHIPPERHQ: No ShipperHQ methods found, letting core handle delivery calculation');
                $this->decorated->calculate($data, $cart, $deliveries, $context);
            }
        }

        $this->logger->info('SHIPPERHQ: Finished delivery calculation', [
            'cart_total' => $cart->getPrice()->getTotalPrice(),
            'deliveries_count' => $deliveries->count(),
            'final_delivery_methods' => array_map(function($delivery) {
                return [
                    'method_id' => $delivery->getShippingMethod()->getId(),
                    'method_name' => $delivery->getShippingMethod()->getName(),
                    'shipping_costs' => $delivery->getShippingCosts()->getTotalPrice()
                ];
            }, $deliveries->getElements())
        ]);
    }

    /**
     * Get the tax rules for a shipping method
     */
    private function getShippingMethodTaxRules(ShippingMethodEntity $shippingMethod, SalesChannelContext $context, Cart $cart): TaxRuleCollection
    {
        $this->logger->info('SHIPPERHQ: Getting tax rules for shipping method', [
            'method_id' => $shippingMethod->getId(),
            'method_name' => $shippingMethod->getName(),
            'tax_type' => $shippingMethod->getTaxType()
        ]);

        // If the shipping method has a fixed tax rate, use that
        if ($shippingMethod->getTaxType() === ShippingMethodEntity::TAX_TYPE_FIXED) {
            $tax = $shippingMethod->getTax();
            if ($tax !== null) {
                $this->logger->info('SHIPPERHQ: Using fixed tax rate', [
                    'tax_id' => $tax->getId(),
                    'tax_rate' => $tax->getTaxRate()
                ]);
                return $context->buildTaxRules($tax->getId());
            }
        }
        
        // If no fixed tax rate is set, use the highest tax rate from the cart
        $highestTaxRate = 0;
        foreach ($cart->getLineItems() as $lineItem) {
            foreach ($lineItem->getPrice()->getCalculatedTaxes() as $tax) {
                $highestTaxRate = max($highestTaxRate, $tax->getTaxRate());
            }
        }

        $this->logger->info('SHIPPERHQ: Using highest cart tax rate', [
            'highest_tax_rate' => $highestTaxRate
        ]);

        // Create a tax rule with the highest tax rate
        return new TaxRuleCollection([
            new TaxRule(
                $highestTaxRate,
                100 // 100% of the shipping cost is taxable
            )
        ]);
    }

    private function calculateShippingCosts(float $rate, ShippingMethodEntity $shippingMethod, Cart $cart, SalesChannelContext $context): CalculatedPrice
    {
        $this->logger->info('SHIPPERHQ: Calculating shipping costs', [
            'rate' => $rate,
            'method_id' => $shippingMethod->getId(),
            'method_name' => $shippingMethod->getName()
        ]);

        // Get tax rules for the shipping method
        $taxRules = $this->getShippingMethodTaxRules($shippingMethod, $context, $cart);

        // Create a price definition for the shipping cost
        $priceDefinition = new \Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition(
            $rate,
            $taxRules,
            1 // Qty of 1
        );

        // Calculate the final price with taxes
        return $this->priceCalculator->calculate($priceDefinition, $context);
    }
}
