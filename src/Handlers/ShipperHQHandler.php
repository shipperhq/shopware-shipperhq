<?php

namespace SHQ\RateProvider\Handlers;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Psr\Log\LoggerInterface;
use SHQ\RateProvider\Service\ShipperHQApiClient;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

class ShipperHQHandler
{

    private LoggerInterface $logger;
    private ShipperHQApiClient $apiClient;
    private EntityRepository $shippingMethodRepository;
    private ContainerInterface $container;


    /**
     * @param LoggerInterface $logger
     * @param ShipperHQApiClient $apiClient
     * @param EntityRepository $shippingMethodRepository
     * @param ContainerInterface $container
     */
    public function __construct(
        LoggerInterface $logger,
        ShipperHQApiClient $apiClient,
        EntityRepository $shippingMethodRepository,
        ContainerInterface $container
    ) {
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->container = $container;
    }
    
    /**
     * Reloads the shipping methods from ShipperHQ
     */
    public function reloadShippingMethods(RequestDataBag $dataBag): array
    {
        $success = ['success' => false];
        $context = Context::createDefaultContext();

        $this->logger->info('reloadShippingMethods');

       try {
            // 1. Get allowed methods from ShipperHQ
            $newAllowedMethods = $this->apiClient->getAllowedMethods();

            $this->logger->info('SHIPPERHQ: Allowed methods: ' . print_r($newAllowedMethods, true));

            if (empty($newAllowedMethods)) {
                throw new \Exception('SHIPPERHQ: No shipping methods returned from allowed methods call');
            }

            // 2. Get existing methods from Shopware
            $criteria = new Criteria();
            
            // Get all shipping methods - we'll filter for ShipperHQ methods in the code
            // This approach is safer when other plugins might be using custom fields
            $existingMethods = $this->shippingMethodRepository->search($criteria, $context);
            
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
                // Create the code as carrierCode-methodCode
                $methodId = $newAllowedMethod['carrierCode'] . '-' . $newAllowedMethod['methodCode'];
                // Create the name as title-method name
                $carrierTitleMethodName = $newAllowedMethod['carrierTitle'] . ' - ' . $newAllowedMethod['methodName'];
                $methodDescription = 'ShipperHQ: ' . $newAllowedMethod['carrierTitle'] . ' - ' . $newAllowedMethod['methodName'];

                $this->logger->info('Processing ShipperHQ method: ' . $carrierTitleMethodName);

                if (!$methodId) {
                    $this->logger->error('SHIPPERHQ: No method ID found for method: ' . $carrierTitleMethodName);
                    continue;
                }

                // Initialize exists flag
                $exists = false;
                
                // see if have any methods
                if (!empty($shipperhqMethods)) {
                    // Check if method already exists
                    foreach ($shipperhqMethods as $existingMethod) {
                        $customFields = $existingMethod->getCustomFields();
                        // We already know customFields and shipperhq_method_id exist from our filtering above
                        if ($customFields['shipperhq_method_id'] === $methodId) {
                            $exists = true;
                            // Update existing method
                            $this->updateShippingMethod($existingMethod->getId(), $newAllowedMethod, 
                                                        $methodId, $carrierTitleMethodName, $methodDescription,
                                                         $context);
                            break;
                        }
                    }
                }

                // Create new method if it doesn't exist
                if (!$exists) {
                    $this->createShippingMethod($newAllowedMethod, $methodId, $carrierTitleMethodName, 
                                $methodDescription, $context);
                }
            }
            
            // 4. Delete shipping methods that are no longer returned by ShipperHQ
            $this->deleteObsoleteShippingMethods($shipperhqMethods, $activeMethodIds, $context);

            $success['success'] = true;
            $success['methods'] = $newAllowedMethods;

        } catch (\Exception $e) {
            $this->logger->error('Error reloading shipping methods: ' . $e->getMessage());
            $success['error'] = $e->getMessage();
        }

        return $success;
    }

    /**
     * Creates a new shipping method in Shopware DB
     * 
     * @param array $newAllowedMethod   Array of new allowed method from SHQ DB
     * @param Context $context   Context of the request
     */
    private function createShippingMethod(array $newAllowedMethod, string $methodId, 
                        string $carrierTitleMethodName, string $methodDescription, 
                        Context $context): void
    {
        $this->logger->info('Creating shipping method: ' . $methodDescription);
        $id = Uuid::randomHex();
        
        // Get a default delivery time ID
        $deliveryTimeId = $this->getDeliveryTimeId($context);

        
        $data = [
            'id' => $id,
            'name' => $carrierTitleMethodName ?? 'ShipperHQ Method',
            'active' => true,
            'description' => $methodDescription ?? '',
            'deliveryTimeId' => $deliveryTimeId,
            'technicalName' => $methodId,
            'customFields' => [
                'shipperhq_method_id' => $methodId,
                'shipperhq_method_code' => $newAllowedMethod['methodCode'] ?? '',
                'shipperhq_method_name' => $newAllowedMethod['methodName'] ?? '',
                'shipperhq_carrier_code' => $newAllowedMethod['carrierCode'] ?? '',
                'shipperhq_carrier_title' => $newAllowedMethod['carrierTitle'] ?? ''
            ],
            'availabilityRule' => [
                'name' => 'All customers',
                'priority' => 0
            ]
        ];

        $this->shippingMethodRepository->create([$data], $context);
    }


    /**
     * Updates a shipping method that already exists in Shopware DB with latest values from SHQ DB
     * 
     * @param string $id   ID of the existing shipping method in Shopware DB    
     * @param array $newAllowedMethod   Array of new allowed method from SHQ DB
     * @param string $methodId   Method ID of the existing shipping method in Shopware DB
     * @param Context $context   Context of the request
     */
    private function updateShippingMethod(string $id, array $newAllowedMethod, 
                                                string $methodId, string $carrierTitleMethodName, 
                                                string $methodDescription, Context $context): void
    {
        $this->logger->info('Updating shipping method: ' . $methodDescription);
        
        // Get a default delivery time ID
        $deliveryTimeId = $this->getDeliveryTimeId($context);
        
        $data = [
            'id' => $id,
            'name' => $carrierTitleMethodName ?? 'ShipperHQ Method',
            'description' => $methodDescription ?? '',
            'deliveryTimeId' => $deliveryTimeId,
            'technicalName' => $methodId,
            'customFields' => [
                'shipperhq_method_id' => $methodId,
                'shipperhq_method_code' => $newAllowedMethod['methodCode'] ?? '',
                'shipperhq_method_name' => $newAllowedMethod['methodName'] ?? '',
                'shipperhq_carrier_code' => $newAllowedMethod['carrierCode'] ?? '',
                'shipperhq_carrier_title' => $newAllowedMethod['carrierTitle'] ?? ''
            ]
        ];

        $this->shippingMethodRepository->update([$data], $context);
    }

        /**
     * Get a default delivery time ID from the repository
     */
    private function getDeliveryTimeId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('min', 0));
        $criteria->addFilter(new EqualsFilter('max', 0));
        $criteria->addFilter(new EqualsFilter('unit', DeliveryTimeEntity::DELIVERY_TIME_DAY));
        
        $deliveryTimeRepository = $this->container->get('delivery_time.repository');
        $deliveryTime = $deliveryTimeRepository->search($criteria, $context)->first();
        
        if ($deliveryTime !== null) {
            return $deliveryTime->getId();
        }
        
        // If no delivery time found, create a default one
        $deliveryTimeId = Uuid::randomHex();
        $deliveryTimeRepository->create([[
            'id' => $deliveryTimeId,
            'min' => 1,
            'max' => 3,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            'name' => 'Standard Delivery',
        ]], $context);
        
        return $deliveryTimeId;
    }

    /**
     * Deletes shipping methods that are no longer returned by ShipperHQ
     * 
     * @param array $shipperhqMethods   Array of shipping methods with ShipperHQ custom fields
     * @param array $activeMethodIds   Array of method IDs that are still active
     * @param Context $context   Context of the request
     */
    private function deleteObsoleteShippingMethods(array $shipperhqMethods, array $activeMethodIds, Context $context): void
    {
        $this->logger->info('Checking for obsolete shipping methods');
        $this->logger->info('Active method IDs: ' . implode(', ', $activeMethodIds));
        
        $deletedCount = 0;

        foreach ($shipperhqMethods as $method) {
            $customFields = $method->getCustomFields();
            $methodId = $customFields['shipperhq_method_id'] ?? '';
            $methodName = $method->getName();

            if (!in_array($methodId, $activeMethodIds)) {
                $this->logger->info('Deleting obsolete shipping method: ' . $methodName . ' (ID: ' . $methodId . ')');
                $this->shippingMethodRepository->delete([['id' => $method->getId()]], $context);
                $deletedCount++;
            }
        }
        
        $this->logger->info('Deleted ' . $deletedCount . ' obsolete shipping methods');
    }

}