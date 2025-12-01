# Testing Guide for ShipperHQ Plugin

## Overview

This document provides comprehensive testing information for the ShipperHQ Shopware plugin, specifically focusing on the improved cache key implementation for the `ShippingMethodRouteSubscriber`.

### 2. Test Suite Structure
```
tests/
├── Unit/                                    # Unit tests
│   └── Feature/Checkout/
│       ├── Rating/Subscriber/
│       │   └── ShippingMethodRouteSubscriberTest.php
│       └── Service/
│           └── RateCacheKeyGeneratorTest.php
├── Integration/                             # Integration tests
│   └── Feature/Checkout/Rating/Subscriber/
│       └── ShippingMethodRouteSubscriberIntegrationTest.php
├── README.md                               # Test documentation
└── run-tests.sh                            # Test runner script
```

## Key Test Cases

### Unit Tests

#### ShippingMethodRouteSubscriberTest
- ✅ **Event Subscription**: Verifies the subscriber listens to the correct event
- ✅ **Valid Cart Scenario**: Tests cache key generation when cart is available
- ✅ **Null Cart Scenario**: Ensures graceful handling when no cart exists
- ✅ **Empty Parts Array**: Tests behavior with empty initial cache parts
- ✅ **Preserve Existing Parts**: Verifies existing cache parts are maintained
- ✅ **Overwrite SHQ Key**: Tests replacement of existing SHQ cache keys

#### RateCacheKeyGeneratorTest
- ✅ **Guest Customer**: Tests key generation for guest users
- ✅ **Customer with Address**: Tests key generation with customer data
- ✅ **Key Consistency**: Verifies identical inputs produce identical keys
- ✅ **Different Carts**: Ensures different carts produce different keys
- ✅ **Different Contexts**: Verifies different contexts produce different keys
- ✅ **Empty Cart**: Tests behavior with empty cart

### Integration Tests

#### ShippingMethodRouteSubscriberIntegrationTest
- ✅ **Real Event Handling**: Tests with actual Shopware event system
- ✅ **Cart Integration**: Tests with real cart objects
- ✅ **Context Integration**: Tests with real sales channel contexts
- ✅ **Cache Key Consistency**: Verifies keys remain consistent across calls
- ✅ **Different Cart Scenarios**: Tests with various cart configurations

## Running the Tests

### Prerequisites
```bash
# Install dependencies
composer install

# Make test runner executable
chmod +x run-tests.sh
```

### Quick Test Run
```bash
# Run all tests
./run-tests.sh

# Or run specific test suites
./vendor/bin/phpunit tests/Unit --configuration phpunit.xml
./vendor/bin/phpunit tests/Integration --configuration phpunit.xml
```

### Individual Test Classes
```bash
# Test the main subscriber
./vendor/bin/phpunit tests/Unit/Feature/Checkout/Rating/Subscriber/ShippingMethodRouteSubscriberTest.php

# Test the cache key generator
./vendor/bin/phpunit tests/Unit/Feature/Checkout/Service/RateCacheKeyGeneratorTest.php

# Test integration
./vendor/bin/phpunit tests/Integration/Feature/Checkout/Rating/Subscriber/ShippingMethodRouteSubscriberIntegrationTest.php
```

## Test Coverage

The test suite provides comprehensive coverage for:

1. **Cache Key Generation**: All scenarios for generating SHQ-specific cache keys
2. **Event Handling**: Proper subscription and handling of cache key events
3. **Integration**: Seamless integration with Shopware's caching system
4. **Edge Cases**: Null carts, empty carts, different customer types
5. **Consistency**: Cache key consistency across multiple calls
6. **Uniqueness**: Different inputs produce different cache keys

## Benefits of This Implementation

### 1. Better Performance
- **Before**: Caching was disabled, causing repeated API calls
- **After**: Proper caching with SHQ-specific keys improves performance

### 2. Cache Efficiency
- SHQ cache keys are included in Shopware's cache parts
- Different carts and contexts get different cache keys
- Cache invalidation works properly

### 3. Maintainability
- Well-tested code with comprehensive test coverage
- Clear separation of concerns
- Easy to debug and extend

### 4. Reliability
- Handles edge cases gracefully
- Consistent behavior across different scenarios
- Integration tests ensure real-world compatibility

## Continuous Integration

The test suite is designed for CI/CD pipelines:
- No external dependencies
- Fast execution
- Clear pass/fail indicators
- Comprehensive coverage reporting

## Future Enhancements

The test structure supports easy addition of:
- Performance tests
- Load tests
- Additional integration scenarios
- Mock API response tests
- Cache invalidation tests

## Conclusion

This comprehensive test suite ensures that the improved cache key implementation works correctly and provides confidence for production deployment. The tests cover all critical scenarios and edge cases, making the codebase more reliable and maintainable.
