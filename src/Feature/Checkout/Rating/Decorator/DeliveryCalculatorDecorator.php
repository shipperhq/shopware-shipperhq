<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Checkout\Rating\Decorator;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Psr\Log\LoggerInterface;
use SHQ\RateProvider\Feature\Checkout\Service\ShippingRateCache;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;

class DeliveryCalculatorDecorator extends DeliveryCalculator
{
    public function __construct(
        private readonly DeliveryCalculator $parent,
        private readonly QuantityPriceCalculator $priceCalculator,
        private readonly LoggerInterface $logger,
        private readonly ShippingRateCache $rateCache,
    ) {}// TODO: Actually learn php

    public function calculate(CartDataCollection $data, Cart $cart, DeliveryCollection $deliveries, SalesChannelContext $context): void
    {

        foreach ($deliveries as $delivery) {
            if ($this->isShipperHQShippingMethod($delivery->getShippingMethod())) {
                $this->calculateShipperHQDelivery($delivery, $cart, $context);
            }
        }

        $this->parent->calculate($data, $cart, $deliveries, $context);
    }

    public function isShipperHQShippingMethod(ShippingMethodEntity $shippingMethod): bool
    {
        $customFields = $shippingMethod->getCustomFields();
        if ($customFields === null) {
            return false;
        }

        return isset($customFields['shipperhq_carrier_code']) && 
               isset($customFields['shipperhq_method_code']) && 
               $customFields['shipperhq_carrier_code'] && 
               $customFields['shipperhq_method_code'];
    }

    public function calculateShipperHQDelivery(Delivery $delivery, Cart $cart, SalesChannelContext $context): void
    {
        // If the shipping method is a ShipperHQ method, we need override the shipping costs
        $shippingMethod = $delivery->getShippingMethod();

        $rate = $this->rateCache->getRateForMethod($shippingMethod->getId(), $cart, $context);

        if ($rate === null) {
            $this->logger->warning('SHIPPERHQ: No rate found for shipping method, skipping', [
                'method_id' => $shippingMethod->getId(),
                'method_name' => $shippingMethod->getName()
            ]);
            return;
        }

        $delivery->setShippingCosts($this->calculateShippingCosts($rate, $shippingMethod, $cart, $context));
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
}
