<?php declare(strict_types=1);

namespace SHQ\RateProvider\Core\Checkout\Shipping\SalesChannel;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;

class ShippingMethodRouteDecorator extends ShippingMethodRoute
{
    private ShippingMethodRoute $decorated;
    private DeliveryCalculator $deliveryCalculator;
    private LoggerInterface $logger;
    private RequestStack $requestStack;
    private CartService $cartService;

    public function __construct(
        ShippingMethodRoute $decorated,
        DeliveryCalculator $deliveryCalculator,
        LoggerInterface $logger,
        RequestStack $requestStack,
        CartService $cartService
    ) {
        $this->decorated = $decorated;
        $this->deliveryCalculator = $deliveryCalculator;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->cartService = $cartService;
    }

    public function getDecorated(): ShippingMethodRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $this->logger->info('SHIPPERHQ: ShippingMethodRouteDecorator::load called', [
            'request_uri' => $request->getUri(),
            'request_method' => $request->getMethod(),
            'criteria' => $criteria->getFilters()
        ]);
        
        $response = $this->decorated->load($request, $context, $criteria);
        $shippingMethods = $response->getShippingMethods();
        
        $this->logger->info('SHIPPERHQ: Got shipping methods from decorated service', [
            'shipping_methods_count' => $shippingMethods->count(),
            'shipping_method_ids' => array_map(function($method) {
                return [
                    'id' => $method->getId(),
                    'name' => $method->getName()
                ];
            }, $shippingMethods->getElements())
        ]);
        
        // Try to get the cart using the CartService
        $cart = null;
        try {
            $cart = $this->cartService->getCart($context->getToken(), $context);
            $this->logger->info('SHIPPERHQ: Cart from CartService', [
                'has_cart' => $cart !== null,
                'cart_id' => $cart ? $cart->getToken() : 'no_cart',
                'line_items_count' => $cart ? $cart->getLineItems()->count() : 0
            ]);
        } catch (\Exception $e) {
            $this->logger->error('SHIPPERHQ: Error getting cart from CartService', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        if (!$cart) {
            $this->logger->info('SHIPPERHQ: No cart found, returning all shipping methods');
            return $response; // Return all shipping methods if no cart is available
        }
        
        // Create a CartDataCollection with the cart's line items
        $data = new CartDataCollection();
        $data->set('lineItems', $cart->getLineItems());
        
        // Create an empty DeliveryCollection
        $deliveries = new DeliveryCollection();
        
        // Calculate deliveries using the delivery calculator
        $this->deliveryCalculator->calculate($data, $cart, $deliveries, $context);
        
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
        
        // Create a list of shipping method IDs that have valid prices
        $validShippingMethodIds = [];
        /** @var Delivery $delivery */
        foreach ($deliveries as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            if ($shippingCosts !== null && $shippingCosts->getTotalPrice() > 0) {
                $validShippingMethodIds[] = $delivery->getShippingMethod()->getId();
            }
        }
        
        $this->logger->info('SHIPPERHQ: Valid shipping method IDs', [
            'valid_method_ids' => $validShippingMethodIds
        ]);
        
        // Filter shipping methods
        $removedCount = 0;
        foreach ($shippingMethods as $key => $shippingMethod) {
            if (!in_array($shippingMethod->getId(), $validShippingMethodIds, true)) {
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
        
        return $response;
    }
} 