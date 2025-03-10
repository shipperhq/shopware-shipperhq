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
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

class ShipperHQHandler
{

    private LoggerInterface $logger;
    private ShipperHQApiClient $apiClient;
    private EntityRepository $shippingMethodRepository;


    /**
     * @param LoggerInterface $logger
     * @param ShipperHQApiClient $apiClient
     * @param EntityRepository $shippingMethodRepository
     */
    public function __construct(
        LoggerInterface $logger,
        ShipperHQApiClient $apiClient,
        EntityRepository $shippingMethodRepository
    ) {
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->shippingMethodRepository = $shippingMethodRepository;
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
            $shqMethods = $this->apiClient->getAllowedMethods();

            if (empty($shqMethods)) {
                throw new \Exception('SHIPPERHQ: No shipping methods returned from allowed methods call');
            }

            // 2. Get existing methods from Shopware
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customFields.shipperhq_method_id', null, 'NEQ'));
            $existingMethods = $this->shippingMethodRepository->search($criteria, $context);
            
            // 3. Process each ShipperHQ method
            foreach ($shqMethods as $shqMethod) {
                $methodId = $shqMethod['methodId'] ?? null;
                if (!$methodId) {
                    continue;
                }

                // Check if method already exists
                $exists = false;
                foreach ($existingMethods as $existingMethod) {
                    if ($existingMethod->getCustomFields()['shipperhq_method_id'] === $methodId) {
                        $exists = true;
                        // Update existing method
                        $this->updateShippingMethod($existingMethod->getId(), $shqMethod, $context);
                        break;
                    }
                }

                // Create new method if it doesn't exist
                if (!$exists) {
                    $this->createShippingMethod($shqMethod, $context);
                }
            }

            $success['success'] = true;
            $success['methods'] = $shqMethods;

        } catch (\Exception $e) {
            $this->logger->error('Error reloading shipping methods: ' . $e->getMessage());
            $success['error'] = $e->getMessage();
        }

        return $success;
    }

    private function createShippingMethod(array $shqMethod, Context $context): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => $shqMethod['name'] ?? 'ShipperHQ Method',
            'active' => true,
            'description' => $shqMethod['description'] ?? '',
            'customFields' => [
                'shipperhq_method_id' => $shqMethod['methodId'],
                'shipperhq_carrier_code' => $shqMethod['carrierCode'] ?? '',
                'shipperhq_carrier_title' => $shqMethod['carrierTitle'] ?? ''
            ],
            'availabilityRule' => [
                'name' => 'All customers',
                'priority' => 0
            ]
        ];

        $this->shippingMethodRepository->create([$data], $context);
    }

    private function updateShippingMethod(string $id, array $shqMethod, Context $context): void
    {
        $data = [
            'id' => $id,
            'name' => $shqMethod['name'] ?? 'ShipperHQ Method',
            'description' => $shqMethod['description'] ?? '',
            'customFields' => [
                'shipperhq_method_id' => $shqMethod['methodId'],
                'shipperhq_carrier_code' => $shqMethod['carrierCode'] ?? '',
                'shipperhq_carrier_title' => $shqMethod['carrierTitle'] ?? ''
            ]
        ];

        $this->shippingMethodRepository->update([$data], $context);
    }

}