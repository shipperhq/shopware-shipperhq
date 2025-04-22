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

namespace SHQ\RateProvider\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\Cart\Event\ShippingMethodPriceCalculationEvent;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use SHQ\RateProvider\Service\ShippingRateCache;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\CartBeforeSerializationEvent;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
    
class BatchShippingRateSubscriber implements EventSubscriberInterface
{
    private ShippingRateCache $rateCache;
    private LoggerInterface $logger;
    private EntityRepository $shippingMethodRepository;
    private QuantityPriceCalculator $priceCalculator;
    private PercentageTaxRuleBuilder $percentageTaxRuleBuilder;

    public function __construct(
        ShippingRateCache $rateCache,
        LoggerInterface $logger,
        EntityRepository $shippingMethodRepository,
        QuantityPriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
        $this->rateCache = $rateCache;
        $this->logger = $logger;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ShippingMethodPriceCalculationEvent::class => 'onShippingMethodPriceCalculation',
            CheckoutOrderPlacedEvent::class => 'onCheckoutOrderPlaced',
            CartBeforeSerializationEvent::class => 'onCartBeforeSerialization',
            CartChangedEvent::class => 'onCartChanged',
            CartVerifyPersistEvent::class => 'onCartVerifyPersist'
        ];
    }

    public function onShippingMethodPriceCalculation(ShippingMethodPriceCalculationEvent $event): void
    {
        $this->logger->info('SHIPPERHQ: ShippingMethodPriceCalculationEvent triggered', [
            'event_class' => get_class($event),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $shippingMethod = $event->getShippingMethod();
        $context = $event->getContext();
        $cart = $event->getCart();

        $this->logger->debug('SHIPPERHQ: Processing shipping method price calculation', [
            'method_id' => $shippingMethod->getId(),
            'method_name' => $shippingMethod->getName(),
            'is_shipperhq' => $shippingMethod->getCustomFields()['shipperhq_method'] ?? false,
            'cart_total' => $cart->getPrice()->getTotalPrice(),
            'cart_quantity' => $cart->getLineItems()->getQuantity(),
            'context_currency' => $context->getCurrencyId()
        ]);

        // Skip if not a ShipperHQ method
        if (!($shippingMethod->getCustomFields()['shipperhq_method'] ?? false)) {
            $this->logger->debug('SHIPPERHQ: Not a ShipperHQ method, skipping', [
                'method_id' => $shippingMethod->getId(),
                'method_name' => $shippingMethod->getName()
            ]);
            return;
        }

        try {
            // Get rates from cache or API
            $rates = $this->rateCache->getRates($cart, $context);
            $this->logger->debug('SHIPPERHQ: Got rates', ['rates' => $rates]);

            if (empty($rates)) {
                $this->logger->warning('SHIPPERHQ: No rates found for method', [
                    'method_id' => $shippingMethod->getId(),
                    'method_name' => $shippingMethod->getName()
                ]);
                return;
            }

            // Find matching rate for this method
            $methodId = $shippingMethod->getId();
            if (!isset($rates[$methodId])) {
                $this->logger->warning('SHIPPERHQ: No rate found for method', [
                    'method_id' => $methodId,
                    'method_name' => $shippingMethod->getName()
                ]);
                return;
            }

            $rate = $rates[$methodId];
            $this->logger->debug('SHIPPERHQ: Found rate for method', [
                'method_id' => $methodId,
                'method_name' => $shippingMethod->getName(),
                'rate' => $rate
            ]);

            // Create price with tax rules
            $price = new \Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price(
                $context->getCurrencyId(),
                $rate['net'],
                $rate['gross'],
                false
            );

            $priceCollection = new \Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection([$price]);
            
            // Create tax rule
            $taxRule = $this->percentageTaxRuleBuilder->build(
                $rate['tax_rate'] ?? 0,
                $context->getTaxState()
            );

            // Set the calculated price with tax rule
            $event->setCalculatedPrice($price);
            $event->setTaxRule($taxRule);

            $this->logger->debug('SHIPPERHQ: Set calculated price', [
                'method_id' => $methodId,
                'method_name' => $shippingMethod->getName(),
                'price' => $price->getGross(),
                'tax_rate' => $rate['tax_rate'] ?? 0
            ]);

        } catch (\Exception $e) {
            $this->logger->error('SHIPPERHQ: Error calculating shipping price', [
                'method_id' => $shippingMethod->getId(),
                'method_name' => $shippingMethod->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function onCheckoutOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $this->logger->info('SHIPPERHQ: CheckoutOrderPlacedEvent triggered', [
            'event_class' => get_class($event),
            'timestamp' => date('Y-m-d H:i:s'),
            'order_id' => $event->getOrder()->getId()
        ]);
        
        $this->rateCache->clearCache();
        $this->logger->debug('SHIPPERHQ: Cleared rate cache after order placement');
    }

    public function onCartBeforeSerialization(CartBeforeSerializationEvent $event): void
    {
        $this->logger->info('SHIPPERHQ: CartBeforeSerializationEvent triggered', [
            'event_class' => get_class($event),
            'timestamp' => date('Y-m-d H:i:s'),
            'cart_total' => $event->getCart()->getPrice()->getTotalPrice()
        ]);
        
        $this->rateCache->clearCache();
        $this->logger->debug('SHIPPERHQ: Cleared rate cache before cart serialization');
    }

    public function onCartChanged(CartChangedEvent $event): void
    {
        $this->logger->info('SHIPPERHQ: CartChangedEvent triggered', [
            'event_class' => get_class($event),
            'timestamp' => date('Y-m-d H:i:s'),
            'cart_total' => $event->getCart()->getPrice()->getTotalPrice()
        ]);

        $this->rateCache->clearCache();
        $this->logger->debug('SHIPPERHQ: Cleared rate cache after cart changes');
    }

    public function onCartVerifyPersist(CartVerifyPersistEvent $event): void
    {
        $this->logger->info('SHIPPERHQ: CartVerifyPersistEvent triggered', [
            'event_class' => get_class($event),
            'timestamp' => date('Y-m-d H:i:s'),
            'cart_total' => $event->getCart()->getPrice()->getTotalPrice()
        ]);
        
        $this->rateCache->clearCache();
        $this->logger->debug('SHIPPERHQ: Cleared rate cache before cart persistence');
    }

    /**
     * Check if a shipping method is a ShipperHQ method
     *
     * @param ShippingMethodEntity $method
     * @return bool
     */
    private function isShipperHQMethod(ShippingMethodEntity $method): bool
    {
        return str_starts_with($method->getTechnicalName(), 'shipperhq_');
    }

    /**
     * Create a calculated price object with proper tax calculation
     *
     * @param float $price
     * @param ShippingMethodEntity $shippingMethod
     * @param \Shopware\Core\Checkout\Cart\Cart $cart
     * @param \Shopware\Core\System\SalesChannel\SalesChannelContext $context
     * @return CalculatedPrice
     */
    private function createCalculatedPrice(
        float $price, 
        ShippingMethodEntity $shippingMethod, 
        \Shopware\Core\Checkout\Cart\Cart $cart, 
        \Shopware\Core\System\SalesChannel\SalesChannelContext $context
    ): CalculatedPrice {
        // Get tax rules based on the shipping method's tax type
        $taxRules = $this->getTaxRules($shippingMethod, $cart, $context);
        
        // Create a price definition with the tax rules
        $priceDefinition = new QuantityPriceDefinition($price, $taxRules, 1);
        
        // Calculate the final price with taxes
        return $this->priceCalculator->calculate($priceDefinition, $context);
    }
    
    /**
     * Get tax rules based on the shipping method's tax type
     *
     * @param ShippingMethodEntity $shippingMethod
     * @param \Shopware\Core\Checkout\Cart\Cart $cart
     * @param \Shopware\Core\System\SalesChannel\SalesChannelContext $context
     * @return TaxRuleCollection
     */
    private function getTaxRules(
        ShippingMethodEntity $shippingMethod, 
        \Shopware\Core\Checkout\Cart\Cart $cart, 
        \Shopware\Core\System\SalesChannel\SalesChannelContext $context
    ): TaxRuleCollection {
        // Get the shipping method's tax type
        $taxType = $shippingMethod->getTaxType();
        
        switch ($taxType) {
            case ShippingMethodEntity::TAX_TYPE_HIGHEST:
                // Use the highest tax rate from the cart
                $highestTaxRule = $cart->getLineItems()->getPrices()->getHighestTaxRule();
                if ($highestTaxRule) {
                    return new TaxRuleCollection([$highestTaxRule]);
                }
                break;
                
            case ShippingMethodEntity::TAX_TYPE_FIXED:
                // Use the fixed tax rate specified for the shipping method
                $taxId = $shippingMethod->getTaxId();
                if ($taxId) {
                    return $context->buildTaxRules($taxId);
                }
                break;
                
            case ShippingMethodEntity::TAX_TYPE_AUTO:
            default:
                // Calculate tax proportionally based on the cart items
                return $this->percentageTaxRuleBuilder->buildRules(
                    $cart->getLineItems()->getPrices()->sum()
                );
        }
        
        // Fallback to an empty tax rule collection (no tax)
        return new TaxRuleCollection();
    }
} 