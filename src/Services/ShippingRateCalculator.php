<?php declare(strict_types=1);

namespace SHQ\RateProvider\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ShippingRateCalculator
{
    public function calculate(SalesChannelContext $context): array
    {
        // Hardcoded rates for testing
        return [
            [
                'name' => 'Standard Ground',
                'price' => 9.99,
                'deliveryTime' => '5-7 business days'
            ],
            [
                'name' => 'Express Shipping',
                'price' => 24.99,
                'deliveryTime' => '2-3 business days'
            ],
            [
                'name' => 'Next Day Air',
                'price' => 49.99,
                'deliveryTime' => '1 business day'
            ],
        ];
    }
}
