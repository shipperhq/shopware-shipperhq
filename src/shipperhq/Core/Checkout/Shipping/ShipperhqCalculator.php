<?php

// Custom Shipping Method Calculator
namespace Shipperhq\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Cart\ShippingCalculator;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ShipperhqCalculator extends ShippingCalculator
{
    private QuantityPriceCalculator $calculator;

    public function __construct(QuantityPriceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function calculate(Cart $cart, SalesChannelContext $context, ShippingMethodEntity $shippingMethod): CalculatedPrice
    {
        // Get cart total weight or other relevant factors
        $weight = 0;
        foreach ($cart->getLineItems() as $lineItem) {
            $weight += $lineItem->getPayload()['weight'] ?? 0;
        }

        // Calculate shipping cost based on weight or other factors
        $shippingCost = $this->calculateShippingCost($weight);

        // Create price definition
        $priceDefinition = new QuantityPriceDefinition(
            $shippingCost,
            new TaxRuleCollection([]),
            1
        );

        // Calculate final price
        return $this->calculator->calculate(
            $priceDefinition,
            $context
        );
    }

    private function calculateShippingCost(float $weight): float
    {
        // Implement your custom shipping cost calculation logic here
        $baseCost = 5.00;
        
        if ($weight <= 1) {
            return $baseCost;
        } elseif ($weight <= 5) {
            return $baseCost + ($weight * 2);
        } else {
            return $baseCost + ($weight * 3);
        }
    }
}

