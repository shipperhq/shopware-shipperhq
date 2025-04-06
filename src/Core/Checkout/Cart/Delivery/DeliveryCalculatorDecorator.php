<?php declare(strict_types=1);

namespace SHQ\RateProvider\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Shipping\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodPriceEntity;
use Shopware\Core\Framework\Util\FloatComparator;
use SHQ\RateProvider\Service\ShippingRateCache;

class DeliveryCalculatorDecorator extends DeliveryCalculator
{
    private DeliveryCalculator $decorated;
    private LoggerInterface $logger;
    private ShippingRateCache $rateCache;
    private QuantityPriceCalculator $priceCalculator;
    private PercentageTaxRuleBuilder $percentageTaxRuleBuilder;

    public function __construct(
        DeliveryCalculator $decorated,
        QuantityPriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder,
        LoggerInterface $logger,
        ShippingRateCache $rateCache
    ) {
        parent::__construct($priceCalculator, $percentageTaxRuleBuilder);
        $this->decorated = $decorated;
        $this->logger = $logger;
        $this->rateCache = $rateCache;
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public function calculate(CartDataCollection $data, Cart $cart, DeliveryCollection $deliveries, SalesChannelContext $context): void
    {
        $this->logger->info('SHIPPERHQ: Starting delivery calculation', [
            'cart_total' => $cart->getPrice()->getTotalPrice(),
            'cart_quantity' => $cart->getLineItems()->count(),
            'context_currency' => $context->getCurrency()->getIsoCode()
        ]);

        // Let the core handle the calculation first
        $this->decorated->calculate($data, $cart, $deliveries, $context);

        // Get ShipperHQ rates
        $rates = $this->rateCache->getRates($cart, $context);
        $this->logger->info('SHIPPERHQ: Got rates from cache', [
            'rates_count' => count($rates),
            'rates' => $rates
        ]);

        // Update shipping costs for ShipperHQ methods
        foreach ($deliveries as $delivery) {
            $shippingMethod = $delivery->getShippingMethod();
            
            $this->logger->info('SHIPPERHQ: Processing shipping method', [
                'method_id' => $shippingMethod->getId(),
                'method_name' => $shippingMethod->getName(),
                'technical_name' => $shippingMethod->getTechnicalName(),
                'is_shipperhq' => strpos($shippingMethod->getTechnicalName(), 'shq') === 0
            ]);

            // Skip if not a ShipperHQ method
            if (strpos($shippingMethod->getTechnicalName(), 'shq') === 0) {
                $rate = $this->rateCache->getRateForMethod($shippingMethod->getId(), $cart, $context);
                
                if ($rate === null) {
                    $this->logger->warning('SHIPPERHQ: No rate found for shipping method', [
                        'method_id' => $shippingMethod->getId(),
                        'method_name' => $shippingMethod->getName()
                    ]);
                    continue;
                }

                $this->logger->info('SHIPPERHQ: Setting shipping costs', [
                    'method_id' => $shippingMethod->getId(),
                    'method_name' => $shippingMethod->getName(),
                    'rate' => $rate
                ]);

                // Create a price definition with the ShipperHQ rate
                $priceDefinition = new \Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition(
                    $rate,
                    $this->percentageTaxRuleBuilder->buildRules($shippingMethod),
                    $context->getCurrency()->getDecimalPrecision()
                );

                // Set the shipping costs
                $delivery->setShippingCosts(
                    $this->priceCalculator->calculate($priceDefinition, $context)
                );
            }
        }

        $this->logger->info('SHIPPERHQ: Finished delivery calculation', [
            'cart_total' => $cart->getPrice()->getTotalPrice(),
            'deliveries_count' => $cart->getDeliveries()->count()
        ]);
    }
} 