# ShipperHQ Plugin Tests

This directory contains comprehensive tests for the ShipperHQ Shopware plugin.

## Test Structure

```
tests/
├── Unit/                           # Unit tests for individual components
│   └── Feature/
│       └── Checkout/
│           ├── Rating/
│           │   └── Subscriber/
│           │       └── ShippingMethodRouteSubscriberTest.php
│           └── Service/
│               └── RateCacheKeyGeneratorTest.php
├── Integration/                    # Integration tests with Shopware components
│   └── Feature/
│       └── Checkout/
│           └── Rating/
│               └── Subscriber/
│                   └── ShippingMethodRouteSubscriberIntegrationTest.php
└── README.md                       # This file
```

## Running Tests

### Quick Start
```bash
# From the plugin root directory
./run-tests.sh
```

### Individual Test Suites
```bash
# Run only unit tests
./vendor/bin/phpunit tests/Unit --configuration phpunit.xml

# Run only integration tests
./vendor/bin/phpunit tests/Integration --configuration phpunit.xml

# Run all tests
./vendor/bin/phpunit --configuration phpunit.xml
```

### Specific Test Classes
```bash
# Test the ShippingMethodRouteSubscriber
./vendor/bin/phpunit tests/Unit/Feature/Checkout/Rating/Subscriber/ShippingMethodRouteSubscriberTest.php

# Test the RateCacheKeyGenerator
./vendor/bin/phpunit tests/Unit/Feature/Checkout/Service/RateCacheKeyGeneratorTest.php
```

## Test Coverage

### Unit Tests
- **ShippingMethodRouteSubscriberTest**: Tests the cache key subscriber functionality
  - Event subscription
  - Cache key generation with valid cart
  - Handling of null cart scenarios
  - Preservation of existing cache parts
  - Overwriting of existing SHQ cache keys

- **RateCacheKeyGeneratorTest**: Tests the cache key generation logic
  - Key generation with different cart contents
  - Key generation with different customer contexts
  - Key consistency for identical inputs
  - Key uniqueness for different inputs

### Integration Tests
- **ShippingMethodRouteSubscriberIntegrationTest**: Tests integration with Shopware components
  - Real event handling with Shopware's event system
  - Integration with actual cart and context objects
  - Cache key consistency across multiple calls
  - Different cart scenarios

## Test Requirements

- PHP 8.1+
- PHPUnit 10.1+
- Shopware 6.6+
- Composer dependencies installed

## Writing New Tests

When adding new tests:

1. **Unit Tests**: Test individual classes in isolation with mocked dependencies
2. **Integration Tests**: Test interactions with Shopware's core components
3. **Follow Naming Convention**: `{ClassName}Test.php`
4. **Use Descriptive Test Names**: `testMethodNameWithSpecificScenario`
5. **Mock External Dependencies**: Use PHPUnit's mocking capabilities
6. **Test Edge Cases**: Include tests for error conditions and edge cases

## Test Data

Tests use mock objects and test data to avoid dependencies on external systems:
- Mock cart objects with test line items
- Mock sales channel contexts
- Mock customer and address data
- Test product data

## Continuous Integration

These tests are designed to run in CI/CD pipelines:
- No external dependencies
- Deterministic test data
- Fast execution
- Clear pass/fail indicators
