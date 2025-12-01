<?php
namespace SHQ\RateProvider\Feature\ConfigurationHandler\Message;

use Shopware\Core\Framework\Context;
use SHQ\RateProvider\Feature\ConfigurationHandler\Service\RefreshShippingMethodsServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles the async refresh of shipping methods.
 */
#[AsMessageHandler]
class RefreshShippingMethodsHandler
{
    public function __construct(
        private RefreshShippingMethodsServiceInterface $refreshShippingMethodsService
    ) {}

    public function __invoke(RefreshShippingMethodsMessage $message): void
    {
        $context = Context::createDefaultContext();

        // 1. Get allowed methods from ShipperHQ
        $newAllowedMethods = $this->refreshShippingMethodsService->getAllowedMethods();
        if (empty($newAllowedMethods)) {
            throw new \Exception('SHIPPERHQ: No shipping methods returned from allowed methods call');
        }

        // 2. Get existing methods from Shopware
        $existingMethods = $this->refreshShippingMethodsService->getExistingShippingMethods($context);

        // Index existing methods by method_id for fast lookup
        $existingMethodsById = [];
        foreach ($existingMethods as $method) {
            $customFields = $method->getCustomFields();
            if ($customFields !== null && isset($customFields['shipperhq_method_id'])) {
                $existingMethodsById[$customFields['shipperhq_method_id']] = $method;
            }
        }

        // Prepare batch operations
        $toCreate = [];
        $toUpdate = [];
        $activeMethodIds = [];

        foreach ($newAllowedMethods as $newAllowedMethod) {
            $methodId = $newAllowedMethod['carrierCode'] . '-' . $newAllowedMethod['methodCode'];
            $activeMethodIds[] = $methodId;
            $carrierTitleMethodName = $newAllowedMethod['carrierTitle'] . ' - ' . $newAllowedMethod['methodName'];
            $methodDescription = 'ShipperHQ: ' . $newAllowedMethod['carrierTitle'] . ' - ' . $newAllowedMethod['methodName'];

            if (!$methodId) {
                continue;
            }

            if (isset($existingMethodsById[$methodId])) {
                $toUpdate[] = [
                    'id' => $existingMethodsById[$methodId]->getId(),
                    'newAllowedMethod' => $newAllowedMethod,
                    'methodId' => $methodId,
                    'carrierTitleMethodName' => $carrierTitleMethodName,
                    'methodDescription' => $methodDescription,
                ];
            } else {
                $toCreate[] = [
                    'newAllowedMethod' => $newAllowedMethod,
                    'methodId' => $methodId,
                    'carrierTitleMethodName' => $carrierTitleMethodName,
                    'methodDescription' => $methodDescription,
                ];
            }
        }

        // Batch update
        foreach ($toUpdate as $update) {
            $this->refreshShippingMethodsService->updateShippingMethod(
                $update['id'],
                $update['newAllowedMethod'],
                $update['methodId'],
                $update['carrierTitleMethodName'],
                $update['methodDescription'],
                $context
            );
        }

        // Batch create
        foreach ($toCreate as $create) {
            $this->refreshShippingMethodsService->createShippingMethod(
                $create['newAllowedMethod'],
                $create['methodId'],
                $create['carrierTitleMethodName'],
                $create['methodDescription'],
                $context
            );
        }

        // 4. Delete obsolete shipping methods
        $this->refreshShippingMethodsService->deactivateObsoleteShippingMethods(
            array_values($existingMethodsById),
            $activeMethodIds,
            $context
        );
    }
} 