<?php declare(strict_types=1);

namespace SHQ\RateProvider\Core\Checkout\Cart\Delivery;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use SHQ\RateProvider\Service\RateCache;

class DeliveryCalculatorDecorator extends DeliveryCalculator
{
    private DeliveryCalculator $decorated;
    private QuantityPriceCalculator $priceCalculator;
    private PercentageTaxRuleBuilder $percentageTaxRuleBuilder;
    private EntityRepository $shippingMethodRepository;
    private RateCache $rateCache;
    private LoggerInterface $logger;

    public function __construct(
        DeliveryCalculator $decorated,
        QuantityPriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder,
        EntityRepository $shippingMethodRepository,
        RateCache $rateCache,
        LoggerInterface $logger
    ) {
        $this->decorated = $decorated;
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->rateCache = $rateCache;
        $this->logger = $logger;
    }

    public function calculate(CartDataCollection $data, Cart $cart, DeliveryCollection $deliveries, SalesChannelContext $context): void
    {
        $this->logger->info('SHIPPERHQ: Starting delivery calculation', [
            'cart_total' => $cart->getPrice()->getTotalPrice(),
            'cart_quantity' => $cart->getLineItems()->count(),
            'context_currency' => $context->getCurrency()->getIsoCode(),
            'initial_deliveries_count' => $deliveries->count()
        ]);

        // First, let the core calculator handle the initial calculation
        $this->decorated->calculate($data, $cart, $deliveries, $context);

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

        if (!$hasShipperHQMethods) {
            $this->logger->info('SHIPPERHQ: No ShipperHQ methods found, using core delivery calculation');
            return;
        }

        $this->logger->info('SHIPPERHQ: Found ShipperHQ methods, updating delivery calculation');

        // Get ShipperHQ rates
        $rates = $this->rateCache->getRates($cart, $context);
        $this->logger->info('SHIPPERHQ: Got rates from cache', [
            'rates_count' => count($rates),
            'rates' => $rates
        ]);

        // Process each delivery
        foreach ($deliveries as $delivery) {
            $shippingMethod = $delivery->getShippingMethod();
            
            // Check if this is a ShipperHQ shipping method
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
            $rate = $this->rateCache->getRateForMethod($shippingMethod->getId(), $cart, $context);
            
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

            // Update the delivery's shipping costs
            $delivery->setShippingCosts($shippingCosts);

            $this->logger->info('SHIPPERHQ: Updated delivery shipping costs', [
                'method_id' => $shippingMethod->getId(),
                'method_name' => $shippingMethod->getName(),
                'rate' => $rate,
                'shipping_costs' => $shippingCosts->getTotalPrice()
            ]);

            // Remove any existing errors for this shipping method
            $filteredErrors = $cart->getErrors()->filter(function ($error) use ($shippingMethod) {
                $this->logger->info('SHIPPERHQ: Filtering errors', [
                    'error' => $error,
                    'shipping_method' => $shippingMethod->getTranslation('name')
                ]);
                
                // Check if the error is related to this shipping method
                $isShippingMethodError = false;
                
                // Check for ShippingMethodBlockedError
                if ($error instanceof ShippingMethodBlockedError) {
                    $isShippingMethodError = $error->getMessage() === (string) $shippingMethod->getTranslation('name');
                }
                
                // Check for ShippingMethodChangedError
                if ($error instanceof ShippingMethodChangedError) {
                    $isShippingMethodError = strpos($error->getMessage(), (string) $shippingMethod->getTranslation('name')) !== false;
                }
                
                // Keep the error if it's not related to this shipping method
                return !$isShippingMethodError;
            });
            
            // Replace the cart's errors with the filtered errors
            $cart->setErrors($filteredErrors);
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

    private function calculateShippingCosts(float $rate, ShippingMethodEntity $shippingMethod, Cart $cart, SalesChannelContext $context): CalculatedPrice
    {
        $this->logger->info('SHIPPERHQ: Calculating shipping costs', [
            'method_name' => $shippingMethod->getName(),
            'rate' => $rate
        ]);

        // Get tax rules for the shipping method
        $taxRules = $this->getShippingMethodTaxRules($shippingMethod, $cart, $context);

        // Create a price definition with the rate and tax rules
        $priceDefinition = new QuantityPriceDefinition(
            $rate,
            $taxRules,
            1 // Qty of 1
        );

        // Calculate the final price with taxes
        return $this->priceCalculator->calculate($priceDefinition, $context);
    }

    private function getShippingMethodTaxRules(ShippingMethodEntity $shippingMethod, Cart $cart, SalesChannelContext $context): TaxRuleCollection
    {
        $this->logger->info('SHIPPERHQ: Getting tax rules for shipping method', [
            'tax_type' => $shippingMethod->getTaxType()
        ]);

        // If the shipping method has a fixed tax rate, use that
        if ($shippingMethod->getTaxType() === ShippingMethodEntity::TAX_TYPE_FIXED && $shippingMethod->getTax() !== null) {
            $this->logger->info('SHIPPERHQ: Using fixed tax rate', [
                'tax_id' => $shippingMethod->getTax()->getId(),
                'tax_rate' => $shippingMethod->getTax()->getTaxRate()
            ]);
            return $context->buildTaxRules($shippingMethod->getTax()->getId());
        }

        // Otherwise, use the highest tax rate from the cart
        $highestTaxRate = $cart->getPrice()->getTotalPrice() > 0 
            ? $this->percentageTaxRuleBuilder->buildRules($cart->getPrice()->getTotalPrice())->first()->getTaxRate() 
            : 19.0; // Default to 19% if no items in cart

        $this->logger->info('SHIPPERHQ: Using highest cart tax rate', [
            'highest_tax_rate' => $highestTaxRate
        ]);

        return new TaxRuleCollection([
            $this->percentageTaxRuleBuilder->buildRules($cart->getPrice()->getTotalPrice())->first()
        ]);
    }
} 