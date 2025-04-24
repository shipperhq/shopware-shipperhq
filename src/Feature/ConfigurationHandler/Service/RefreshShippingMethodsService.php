<?php

namespace SHQ\RateProvider\Feature\ConfigurationHandler\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Psr\Log\LoggerInterface;
use SHQ\RateProvider\Service\ShipperHQClient;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use SHQ\RateProvider\Feature\ConfigurationHandler\Service\RefreshShippingMethodsServiceInterface;

class RefreshShippingMethodsService implements RefreshShippingMethodsServiceInterface
{
    private ?string $shipperhqTagId = null;

    public function __construct(
        private LoggerInterface $logger,
        private ShipperHQClient $apiClient,
        private EntityRepository $shippingMethodRepository,
        private EntityRepository $tagRepository,
        private EntityRepository $deliveryTimeRepository,
        private EntityRepository $ruleRepository,
        private EntityRepository $salesChannelRepository
    ) {}

    private function getShipperHQTagId(Context $context): string
    {
        if ($this->shipperhqTagId !== null) {
            return $this->shipperhqTagId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'shipperhq_managed'));
        
        $tagId = $this->tagRepository->searchIds($criteria, $context)->firstId();
        
        if ($tagId === null) {
            $tagId = Uuid::randomHex();
            $this->tagRepository->create([[
                'id' => $tagId,
                'name' => 'shipperhq_managed'
            ]], $context);
        }
        
        $this->shipperhqTagId = $tagId;
        return $tagId;
    }

    public function getAllowedMethods(): array
    {
        $this->logger->info('Getting allowed methods from ShipperHQ');
        $newAllowedMethods = $this->apiClient->getAllowedMethods();
        $this->logger->info('SHIPPERHQ: Allowed methods: ' . print_r($newAllowedMethods, true));
        return $newAllowedMethods;
    }

    public function getExistingShippingMethods(Context $context): array
    {
        $criteria = new Criteria();
        return $this->shippingMethodRepository->search($criteria, $context)->getElements();
    }

    public function createShippingMethod(
        array $newAllowedMethod,
        string $methodId,
        string $carrierTitleMethodName,
        string $methodDescription,
        Context $context
    ): void {
        $this->logger->info('Creating shipping method: ' . $methodDescription);
        $id = Uuid::randomHex();
        
        $deliveryTimeId = $this->getDeliveryTimeId($context);
        $salesChannelIds = $this->getActiveSalesChannelIds($context);
        $availabilityRuleId = $this->getAvailabilityRuleId($context);
        $tagId = $this->getShipperHQTagId($context);
        
        $data = [
            'id' => $id,
            'name' => $carrierTitleMethodName ?? 'ShipperHQ Method',
            'active' => true,
            'description' => '',
            'deliveryTimeId' => $deliveryTimeId,
            'technicalName' => $methodId,
            'customFields' => [
                'shipperhq_method_id' => $methodId,
                'shipperhq_method_code' => $newAllowedMethod['methodCode'] ?? '',
                'shipperhq_method_name' => $newAllowedMethod['methodName'] ?? '',
                'shipperhq_carrier_code' => $newAllowedMethod['carrierCode'] ?? '',
                'shipperhq_carrier_title' => $newAllowedMethod['carrierTitle'] ?? ''
            ],
            'tags' => [
                ['id' => $tagId]
            ],
            'availabilityRuleId' => $availabilityRuleId,
            'salesChannels' => $salesChannelIds
        ];

        $this->logger->info('Creating shipping method with data: ', [
            'method_id' => $id,
            'name' => $carrierTitleMethodName,
            'sales_channels' => count($salesChannelIds),
            'availability_rule_id' => $availabilityRuleId
        ]);

        $this->shippingMethodRepository->create([$data], $context);
    }

    public function updateShippingMethod(
        string $id,
        array $newAllowedMethod,
        string $methodId,
        string $carrierTitleMethodName,
        string $methodDescription,
        Context $context
    ): void {
        $this->logger->info('Updating shipping method: ' . $methodDescription);
        
        $deliveryTimeId = $this->getDeliveryTimeId($context);
        $salesChannelIds = $this->getActiveSalesChannelIds($context);
        $availabilityRuleId = $this->getAvailabilityRuleId($context);
        $tagId = $this->getShipperHQTagId($context);
        
        $data = [
            'id' => $id,
            'name' => $carrierTitleMethodName ?? 'ShipperHQ Method',
            'description' => '',
            'deliveryTimeId' => $deliveryTimeId,
            'technicalName' => $methodId,
            'customFields' => [
                'shipperhq_method_id' => $methodId,
                'shipperhq_method_code' => $newAllowedMethod['methodCode'] ?? '',
                'shipperhq_method_name' => $newAllowedMethod['methodName'] ?? '',
                'shipperhq_carrier_code' => $newAllowedMethod['carrierCode'] ?? '',
                'shipperhq_carrier_title' => $newAllowedMethod['carrierTitle'] ?? ''
            ],
            'tags' => [
                ['id' => $tagId]
            ],
            'availabilityRuleId' => $availabilityRuleId,
            'salesChannels' => $salesChannelIds
        ];

        $this->logger->info('Updating shipping method with data: ', [
            'method_id' => $id,
            'name' => $carrierTitleMethodName,
            'sales_channels' => count($salesChannelIds),
            'availability_rule_id' => $availabilityRuleId
        ]);

        $this->shippingMethodRepository->update([$data], $context);
    }

    public function deleteObsoleteShippingMethods(array $shipperhqMethods, array $activeMethodIds, Context $context): void
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

    private function getDeliveryTimeId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('min', 0));
        $criteria->addFilter(new EqualsFilter('max', 0));
        $criteria->addFilter(new EqualsFilter('unit', DeliveryTimeEntity::DELIVERY_TIME_DAY));
        
        $deliveryTime = $this->deliveryTimeRepository->search($criteria, $context)->first();
        
        if ($deliveryTime !== null) {
            return $deliveryTime->getId();
        }
        
        // If no delivery time found, create a default one
        $deliveryTimeId = Uuid::randomHex();
        $this->deliveryTimeRepository->create([[
            'id' => $deliveryTimeId,
            'min' => 1,
            'max' => 3,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            'name' => 'Standard Delivery',
        ]], $context);
        
        return $deliveryTimeId;
    }

    private function getAvailabilityRuleId(Context $context): string
    {
        // Find the 'Cart >= 0' rule by name
        $ruleCriteria = new Criteria();
        $ruleCriteria->addFilter(new EqualsFilter('name', 'Cart >= 0'));
        $ruleId = $this->ruleRepository->searchIds($ruleCriteria, $context)->firstId();
        
        if ($ruleId !== null) {
            return $ruleId;
        }
        
        // If not found, try to find any rule
        $ruleCriteria = new Criteria();
        $ruleCriteria->setLimit(1);
        $ruleId = $this->ruleRepository->searchIds($ruleCriteria, $context)->firstId();
        
        if ($ruleId !== null) {
            return $ruleId;
        }
        
        // If no rule exists, create a new one
        $ruleId = Uuid::randomHex();
        $ruleData = [
            'id' => $ruleId,
            'name' => 'All customers',
            'priority' => 0,
            'description' => 'Rule for all customers',
            'payload' => null,
            'invalid' => false,
            'areas' => null,
            'moduleTypes' => null,
            'customFields' => null,
            'createdAt' => new \DateTimeImmutable(),
            'updatedAt' => new \DateTimeImmutable()
        ];
        
        $this->ruleRepository->create([$ruleData], $context);
        
        return $ruleId;
    }

    private function getActiveSalesChannelIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $salesChannels = $this->salesChannelRepository->search($criteria, $context);
        
        return array_map(function($salesChannel) {
            return ['id' => $salesChannel->getId()];
        }, $salesChannels->getElements());
    }
}
