<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Rating\Decorator;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SHQ\RateProvider\Feature\Rating\Service\ShippingRateCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;

class ShippingMethodRouteDecorator extends ShippingMethodRoute
{
    private static int $requestCounter = 0;

    public function __construct(
        private readonly ShippingMethodRoute $decorated,
        private readonly DeliveryCalculator $deliveryCalculator,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        private readonly CartService $cartService,
        private readonly ShippingRateCache $rateCache
    ) {}

    public function getDecorated(): ShippingMethodRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $requestId = $this->incrementRequestCounter();
        $this->logRequestDetails($request, $criteria, $requestId);

        if ($this->isInitialCall($requestId)) {
            return $this->handleInitialCall($request, $context, $criteria);
        }

        return $this->handleValidationCall($request, $context, $criteria);
    }

    private function incrementRequestCounter(): int
    {
        return ++self::$requestCounter;
    }

    private function logRequestDetails(Request $request, Criteria $criteria, int $requestId): void
    {
        $this->logger->info('SHIPPERHQ: ShippingMethodRouteDecorator::load called', [
            'request_id' => $requestId,
            'request_uri' => $request->getUri(),
            'request_method' => $request->getMethod(),
            'criteria' => $criteria->getFilters(),
            'is_initial_call' => $this->isInitialCall($requestId),
            'is_validation_call' => $this->isValidationCall($requestId)
        ]);
    }

    private function isInitialCall(int $requestId): bool
    {
        return $requestId === 1;
    }

    private function isValidationCall(int $requestId): bool
    {
        return $requestId === 2;
    }

    private function handleInitialCall(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $this->logger->info('SHIPPERHQ: Initial call - returning all shipping methods');
        return $this->decorated->load($request, $context, $criteria);
    }

    private function handleValidationCall(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $response = $this->decorated->load($request, $context, $criteria);
        $shippingMethods = $response->getShippingMethods();
        
        $this->logShippingMethods($shippingMethods);
        
        $cart = $this->getCart($context);
        if (!$cart) {
            return $response;
        }
        
        $this->filterShippingMethods($cart, $context, $shippingMethods);
        
        return $response;
    }

    private function logShippingMethods($shippingMethods): void
    {
        $this->logger->info('SHIPPERHQ: Got shipping methods from decorated service', [
            'shipping_methods_count' => $shippingMethods->count(),
            'shipping_method_ids' => array_map(function($method) {
                return [
                    'id' => $method->getId(),
                    'name' => $method->getName()
                ];
            }, $shippingMethods->getElements())
        ]);
    }

    private function getCart(SalesChannelContext $context): ?Cart
    {
        try {
            $cart = $this->cartService->getCart($context->getToken(), $context);
            $this->logger->info('SHIPPERHQ: Cart from CartService', [
                'has_cart' => $cart !== null,
                'cart_id' => $cart ? $cart->getToken() : 'no_cart',
                'line_items_count' => $cart ? $cart->getLineItems()->count() : 0
            ]);
            return $cart;
        } catch (\Exception $e) {
            $this->logger->error('SHIPPERHQ: Error getting cart from CartService', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function calculateDeliveries(Cart $cart, SalesChannelContext $context): DeliveryCollection
    {
        $data = new CartDataCollection();
        $data->set('lineItems', $cart->getLineItems());
        
        $deliveries = new DeliveryCollection();
        $this->deliveryCalculator->calculate($data, $cart, $deliveries, $context);
        
        $this->logDeliveries($deliveries);
        
        return $deliveries;
    }

    private function logDeliveries(DeliveryCollection $deliveries): void
    {
        $this->logger->info('SHIPPERHQ: Calculated deliveries', [
            'deliveries_count' => $deliveries->count(),
            'delivery_methods' => array_map(function($delivery) {
                return [
                    'method_id' => $delivery->getShippingMethod()->getId(),
                    'method_name' => $delivery->getShippingMethod()->getName(),
                    'shipping_costs' => $delivery->getShippingCosts()->getTotalPrice()
                ];
            }, $deliveries->getElements())
        ]);
    }

    private function filterShippingMethods(Cart $cart, SalesChannelContext $context, ShippingMethodCollection $shippingMethods): void
    {
        $removedCount = 0;
        $rates = $this->rateCache->getRates($cart, $context);
        foreach ($shippingMethods as $key => $shippingMethod) {
            // Remove shipping methods that don't have a rate
            // Keep the non shipperhq shipping methods
            if (!isset($rates[$shippingMethod->getId()]) 
                    && $shippingMethod->getCustomFields() !== null
                    && isset($shippingMethod->getCustomFields()['shipperhq_carrier_code'])
                    && $shippingMethod->getCustomFields()['shipperhq_carrier_code'] !== null) {
                $this->logger->info('SHIPPERHQ: Removing shipping method', [
                    'method_id' => $shippingMethod->getId(),
                    'method_name' => $shippingMethod->getName()
                ]);
                $shippingMethods->remove($key);
                $removedCount++;
            }
        }
        
        $this->logger->info('SHIPPERHQ: Filtered shipping methods', [
            'filtered_methods_count' => $shippingMethods->count(),
            'removed_methods_count' => $removedCount
        ]);
    }
}
