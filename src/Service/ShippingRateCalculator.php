<?php declare(strict_types=1);

namespace SHQ\RateProvider\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Struct\ArrayStruct;

class ShippingRateCalculator
{
    public function calculate(SalesChannelContext $context): array
    {
        $cart = $context->getCart();
        $cartTotal = $cart->getPrice()->getTotalPrice();
        
        // Debug logging
        file_put_contents(
            'var/log/shipping-debug.log',
            sprintf(
                "[%s] Calculate called - Cart Total: %s\n",
                date('Y-m-d H:i:s'),
                $cartTotal
            ),
            FILE_APPEND
        );
        
        // Here you would typically make an API call to ShipperHQ
        // For now, we'll use conditional logic based on cart total
        $rates = [];
        
        // Basic shipping for orders under $50
        if ($cartTotal < 50) {
            $rates[] = [
                'name' => 'Standard Ground',
                'price' => 9.99,
                'deliveryTime' => [
                    'earliest' => 5,
                    'latest' => 7,
                    'unit' => 'day'
                ]
            ];
        }
        
        // Add express shipping for all orders
        $rates[] = [
            'name' => 'Express Shipping',
            'price' => $cartTotal > 100 ? 19.99 : 24.99, // Discount for orders over $100
            'deliveryTime' => [
                'earliest' => 2,
                'latest' => 3,
                'unit' => 'day'
            ]
        ];
        
        // Premium shipping for high-value orders
        if ($cartTotal > 200) {
            $rates[] = [
                'name' => 'Next Day Air',
                'price' => 49.99,
                'deliveryTime' => [
                    'earliest' => 1,
                    'latest' => 1,
                    'unit' => 'day'
                ]
            ];
        }
        
        return $rates;
    }
} 