<?php

namespace SHQ\RateProvider\Feature\ConfigurationHandler\Service;

use Shopware\Core\Framework\Context;

interface RefreshShippingMethodsServiceInterface
{
    /**
     * Get allowed methods from ShipperHQ
     */
    public function getAllowedMethods(): array;

    /**
     * Get existing shipping methods from Shopware
     */
    public function getExistingShippingMethods(Context $context): array;

    /**
     * Creates a new shipping method in Shopware DB
     */
    public function createShippingMethod(
        array $newAllowedMethod,
        string $methodId,
        string $carrierTitleMethodName,
        string $methodDescription,
        Context $context
    ): void;

    /**
     * Updates a shipping method that already exists in Shopware DB
     */
    public function updateShippingMethod(
        string $id,
        array $newAllowedMethod,
        string $methodId,
        string $carrierTitleMethodName,
        string $methodDescription,
        Context $context
    ): void;

    /**
     * Deletes shipping methods that are no longer returned by ShipperHQ
     */
    public function deleteObsoleteShippingMethods(array $shipperhqMethods, array $activeMethodIds, Context $context): void;
} 