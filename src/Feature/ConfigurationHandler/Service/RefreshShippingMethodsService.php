<?php declare(strict_types=1);

/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package SHQ\RateProvider
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace SHQ\RateProvider\Feature\ConfigurationHandler\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use SHQ\RateProvider\Service\ShipperHQClient;

class RefreshShippingMethodsService implements RefreshShippingMethodsServiceInterface
{
    private ?string $shipperhqTagId = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ShipperHQClient $apiClient,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly EntityRepository $tagRepository,
        private readonly EntityRepository $deliveryTimeRepository,
        private readonly EntityRepository $ruleRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $currencyRepository,
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

    /**
     * ENG26-226 Create price matrix entries for all active currencies
     * This is required for Shopware to consider the shipping method valid during checkout
     * The actual price is overridden dynamically by DeliveryCalculatorDecorator
     */
    private function createPriceMatrixEntries(Context $context): array
    {
        $criteria = new Criteria();
        $currencies = $this->currencyRepository->search($criteria, $context);
        
        // Build currency prices - all currencies must be in a single price entry
        $currencyPrices = [];
        foreach ($currencies as $currency) {
            $currencyPrices[] = [
                'currencyId' => $currency->getId(),
                'net' => 0.00,    // Placeholder (overridden by decorator)
                'gross' => 0.00,  // Placeholder (overridden by decorator)
                'linked' => true, // Keep net/gross synchronized
            ];
        }
        
        // Create a single price matrix entry containing all currencies
        $prices = [
            [
                'calculation' => 1, // 1 = per quantity, 2 = per weight, 3 = per price
                'quantityStart' => 1,
                'quantityEnd' => null, // null = unlimited
                'currencyPrice' => $currencyPrices,
            ]
        ];
        
        $this->logger->debug('SHIPPERHQ: Created price matrix entry with currencies', [
            'currency_count' => count($currencyPrices),
        ]);
        
        return $prices;
    }

    public function getAllowedMethods(): array
    {
        $this->logger->info('Getting allowed methods from ShipperHQ');
        $newAllowedMethods = $this->apiClient->getAllowedMethods();
        $this->logger->debug('SHIPPERHQ: Allowed methods retrieved', ['count' => count($newAllowedMethods)]);
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
        $this->logger->debug('SHIPPERHQ: Creating shipping method', ['description' => $methodDescription]);
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
            'technicalName' => 'shipperhq_' . $methodId,
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
            'salesChannels' => $salesChannelIds,
            'prices' => $this->createPriceMatrixEntries($context)
        ];

        $this->logger->debug('SHIPPERHQ: Creating shipping method', [
            'method_id' => $id,
            'name' => $carrierTitleMethodName,
            'sales_channels' => count($salesChannelIds),
            'availability_rule_id' => $availabilityRuleId,
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
        $this->logger->debug('SHIPPERHQ: Updating shipping method', ['description' => $methodDescription]);
        
        $deliveryTimeId = $this->getDeliveryTimeId($context);
        $tagId = $this->getShipperHQTagId($context);
        
        $data = [
            'id' => $id,
            'name' => $carrierTitleMethodName ?? 'ShipperHQ Method',
            'description' => '',
            'deliveryTimeId' => $deliveryTimeId,
            'technicalName' => 'shipperhq_' . $methodId,
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
            'prices' => $this->createPriceMatrixEntries($context)
        ];

        $this->logger->debug('SHIPPERHQ: Updating shipping method', [
            'method_id' => $id,
            'name' => $carrierTitleMethodName
        ]);

        $this->shippingMethodRepository->update([$data], $context);
    }

    public function deactivateObsoleteShippingMethods(array $shipperhqMethods, array $activeMethodIds, Context $context): void
    {
        $this->logger->debug('SHIPPERHQ: Checking for obsolete shipping methods', [
            'active_method_ids' => $activeMethodIds,
        ]);

        $deactivatedCount = 0;

        foreach ($shipperhqMethods as $method) {
            $customFields = $method->getCustomFields();
            $methodId = $customFields['shipperhq_method_id'] ?? '';

            if (!in_array($methodId, $activeMethodIds)) {
                $this->logger->debug('SHIPPERHQ: Deactivating obsolete shipping method', [
                    'method_name' => $method->getName(),
                    'method_id' => $methodId,
                ]);
                $this->shippingMethodRepository->update([[
                    'id' => $method->getId(),
                    'active' => false
                ]], $context);
                $deactivatedCount++;
            }
        }

        $this->logger->info('SHIPPERHQ: Deactivated obsolete shipping methods', [
            'deactivated_count' => $deactivatedCount,
        ]);
    }

    private const DELIVERY_TIME_NAME = 'shipperhq_standard_delivery_time';

    private function getDeliveryTimeId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::DELIVERY_TIME_NAME));

        $deliveryTime = $this->deliveryTimeRepository->search($criteria, $context)->first();

        if ($deliveryTime !== null) {
            return $deliveryTime->getId();
        }

        $deliveryTimeId = Uuid::randomHex();
        $this->deliveryTimeRepository->create([[
            'id' => $deliveryTimeId,
            'min' => 1,
            'max' => 3,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            'name' => self::DELIVERY_TIME_NAME,
        ]], $context);

        return $deliveryTimeId;
    }

    private const AVAILABILITY_RULE_NAME = 'shipperhq_always_available';

    private function getAvailabilityRuleId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::AVAILABILITY_RULE_NAME));
        $ruleId = $this->ruleRepository->searchIds($criteria, $context)->firstId();

        if ($ruleId !== null) {
            return $ruleId;
        }

        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create([[
            'id' => $ruleId,
            'name' => self::AVAILABILITY_RULE_NAME,
            'priority' => 0,
        ]], $context);

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
