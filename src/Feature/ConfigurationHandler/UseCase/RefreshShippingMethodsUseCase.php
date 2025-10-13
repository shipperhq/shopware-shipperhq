<?php
/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package SHQ\RateProvider
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace SHQ\RateProvider\Feature\ConfigurationHandler\UseCase;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use SHQ\RateProvider\Feature\ConfigurationHandler\Service\RefreshShippingMethodsServiceInterface;

class RefreshShippingMethodsUseCase
{
    public function __construct(
        private RefreshShippingMethodsServiceInterface $refreshShippingMethodsService
    ) {}

    public function execute(RequestDataBag $dataBag): array
    {
        $context = Context::createDefaultContext();
        $success = ['success' => false];

        try {
            // 1. Get allowed methods from ShipperHQ
            $newAllowedMethods = $this->refreshShippingMethodsService->getAllowedMethods();

            if (empty($newAllowedMethods)) {
                throw new \Exception('SHIPPERHQ: No shipping methods returned from allowed methods call');
            }

            // 2. Get existing methods from Shopware
            $existingMethods = $this->refreshShippingMethodsService->getExistingShippingMethods($context);
            
            // Filter to only get methods with ShipperHQ custom fields
            $shipperhqMethods = [];
            foreach ($existingMethods as $method) {
                $customFields = $method->getCustomFields();
                if ($customFields !== null && isset($customFields['shipperhq_method_id'])) {
                    $shipperhqMethods[] = $method;
                }
            }
            
            // Create a list of method IDs from ShipperHQ to track which methods are still active
            $activeMethodIds = [];
            foreach ($newAllowedMethods as $newAllowedMethod) {
                $methodId = $newAllowedMethod['carrierCode'] . '-' . $newAllowedMethod['methodCode'];
                $activeMethodIds[] = $methodId;
            }
            
            // 3. Process each ShipperHQ method
            foreach ($newAllowedMethods as $newAllowedMethod) {
                $methodId = $newAllowedMethod['carrierCode'] . '-' . $newAllowedMethod['methodCode'];
                $carrierTitleMethodName = $newAllowedMethod['carrierTitle'] . ' - ' . $newAllowedMethod['methodName'];
                $methodDescription = 'ShipperHQ: ' . $newAllowedMethod['carrierTitle'] . ' - ' . $newAllowedMethod['methodName'];

                if (!$methodId) {
                    continue;
                }

                // Check if method already exists
                $exists = false;
                if (!empty($shipperhqMethods)) {
                    foreach ($shipperhqMethods as $existingMethod) {
                        $customFields = $existingMethod->getCustomFields();
                        if ($customFields['shipperhq_method_id'] === $methodId) {
                            $exists = true;
                            $this->refreshShippingMethodsService->updateShippingMethod(
                                $existingMethod->getId(),
                                $newAllowedMethod,
                                $methodId,
                                $carrierTitleMethodName,
                                $methodDescription,
                                $context
                            );
                            break;
                        }
                    }
                }

                // Create new method if it doesn't exist
                if (!$exists) {
                    $this->refreshShippingMethodsService->createShippingMethod(
                        $newAllowedMethod,
                        $methodId,
                        $carrierTitleMethodName,
                        $methodDescription,
                        $context
                    );
                }
            }
            
            // 4. Delete shipping methods that are no longer returned by ShipperHQ
            $this->refreshShippingMethodsService->deactivateObsoleteShippingMethods($shipperhqMethods, $activeMethodIds, $context);

            $success['success'] = true;
            $success['methods'] = $newAllowedMethods;

        } catch (\Exception $e) {
            $success['error'] = $e->getMessage();
        }

        return $success;
    }
}
