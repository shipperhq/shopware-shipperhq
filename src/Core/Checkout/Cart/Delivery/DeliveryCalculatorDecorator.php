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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCollection;

class DeliveryCalculatorDecorator extends DeliveryCalculator
{
    private DeliveryCalculator $decorated;
    private LoggerInterface $logger;
    private ShippingRateCache $rateCache;
    private QuantityPriceCalculator $priceCalculator;
    private PercentageTaxRuleBuilder $percentageTaxRuleBuilder;
    private EntityRepository $shippingMethodRepository;

    public function __construct(
        DeliveryCalculator $decorated,
        QuantityPriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder,
        LoggerInterface $logger,
        ShippingRateCache $rateCache,
        EntityRepository $shippingMethodRepository
    ) {
        parent::__construct($priceCalculator, $percentageTaxRuleBuilder);
        $this->decorated = $decorated;
        $this->logger = $logger;
        $this->rateCache = $rateCache;
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
        $this->shippingMethodRepository = $shippingMethodRepository;
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

        // Get all physical line items that need shipping
        $deliveryLineItems = $cart->getLineItems()->filter(function ($lineItem) {
            return $lineItem->getDeliveryInformation() && !$lineItem->getDeliveryInformation()->getFreeDelivery();
        });

        if ($deliveryLineItems->count() === 0) {
            $this->logger->info('SHIPPERHQ: No physical line items found that need shipping');
            return;
        }

        // Convert LineItemCollection to DeliveryPositionCollection
        $deliveryPositions = new \Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection();
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

        // Get all available shipping methods for the current sales channel
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));
        
        $shippingMethods = $this->shippingMethodRepository->search($criteria, $context->getContext())->getEntities();

        // Process all shipping methods
        foreach ($shippingMethods as $shippingMethod) {
            // Check if this is a ShipperHQ shipping method using custom fields
            $customFields = $shippingMethod->getCustomFields() ?? [];
            $isShipperHQ = isset($customFields['shipperhq_carrier_code']) && 
                           isset($customFields['shipperhq_method_code']);
            
            if (!$isShipperHQ) {
                continue;
            }

            // Get the rate for this shipping method
            $rate = $this->rateCache->getRateForMethod($shippingMethod->getId(), $cart, $context);
            
            if ($rate === null) {
                $this->logger->warning('SHIPPERHQ: No rate found for shipping method', [
                    'method_id' => $shippingMethod->getId(),
                    'method_name' => $shippingMethod->getName()
                ]);
                continue;
            }

            $this->logger->info('SHIPPERHQ: Found rate for shipping method', [
                'method_name' => $shippingMethod->getName(),
                'rate' => $rate
            ]);

            // Create a delivery for the ShipperHQ method
            $delivery = new Delivery(
                $deliveryPositions,
                new \Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate(
                    new \DateTimeImmutable(),
                    new \DateTimeImmutable()
                ),
                $shippingMethod,
                $context->getShippingLocation(),
                new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(
                    $rate,
                    $rate,
                    new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(),
                    new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()
                )
            );

            // Add the delivery to the collection
            $deliveries->add($delivery);

            $this->logger->info('SHIPPERHQ: Added delivery for ShipperHQ method', [
                'method_name' => $shippingMethod->getName(),
                'line_items_count' => $deliveryPositions->count(),
                'shipping_costs' => $rate
            ]);
        }

        $this->logger->info('SHIPPERHQ: Finished delivery calculation', [
            'cart_total' => $cart->getPrice()->getTotalPrice(),
            'deliveries_count' => $deliveries->count()
        ]);
    }

    /**
     * Get the tax rules for a shipping method
     */
    private function getShippingMethodTaxRules(ShippingMethodEntity $shippingMethod, SalesChannelContext $context, Cart $cart): \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection
    {
        if ($shippingMethod->getTaxType() === ShippingMethodEntity::TAX_TYPE_FIXED) {
            $tax = $shippingMethod->getTax();
            if ($tax !== null) {
                return $context->buildTaxRules($tax->getId());
            }
        }
        
        // Default to highest tax rate from the cart
        return $this->percentageTaxRuleBuilder->buildRules(
            $cart->getLineItems()->getPrices()->sum()
        );
    }
} 