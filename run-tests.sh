#!/bin/bash

# ShipperHQ Test Runner Script
# This script runs the test suite for the ShipperHQ plugin

echo "🚀 Running ShipperHQ Plugin Tests"
echo "=================================="

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "❌ Error: composer.json not found. Please run this script from the plugin root directory."
    exit 1
fi

# Install dependencies if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo "📦 Installing dependencies..."
    composer install --no-dev
    composer install
fi

# Run unit tests
echo ""
echo "🧪 Running Unit Tests..."
echo "------------------------"
./vendor/bin/phpunit tests/Unit --configuration phpunit.xml

# Run integration tests
echo ""
echo "🔗 Running Integration Tests..."
echo "-------------------------------"
./vendor/bin/phpunit tests/Integration --configuration phpunit.xml

# Run all tests
echo ""
echo "🎯 Running All Tests..."
echo "----------------------"
./vendor/bin/phpunit --configuration phpunit.xml

echo ""
echo "✅ Test run completed!"
