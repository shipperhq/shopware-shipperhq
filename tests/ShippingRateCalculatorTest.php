<?php

namespace SHQ\RateProvider\Tests;

use PHPUnit\Framework\TestCase;
use SHQ\RateProvider\ShippingRateCalculator;
use SHQ\RateProvider\Entity\SalesChannelContext;
use SHQ\RateProvider\Behaviour\IntegrationTestBehaviour;

class ShippingRateCalculatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCalculate(): void
    {
        // Set up test data
        $context = $this->createMock(SalesChannelContext::class);
        
        $calculator = new ShippingRateCalculator();
        $rates = $calculator->calculate($context);
        
        // Debug output during tests
        fwrite(STDERR, print_r($rates, true));
        
        // Assertions
        $this->assertNotEmpty($rates);
    }
} 