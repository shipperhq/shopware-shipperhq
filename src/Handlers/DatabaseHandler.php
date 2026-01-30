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

namespace SHQ\RateProvider\Handlers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class DatabaseHandler
{
    private Connection $connection;
    private EntityRepository $customFieldSetRepository;
    private EntityRepository $customFieldRepository;

    public function __construct(
        Connection $connection,
        EntityRepository $customFieldSetRepository,
        EntityRepository $customFieldRepository
    ) {
        $this->connection = $connection;
        $this->customFieldSetRepository = $customFieldSetRepository;
        $this->customFieldRepository = $customFieldRepository;
    }

    public function removeShipperHQTables(Context $context): void
    {
        // Remove custom field sets
        $this->removeCustomFields($context);
        
        // Remove system configuration
        $this->removeConfiguration();
    }

    private function removeCustomFields(Context $context): void
    {
        // Custom field names defined in CustomFieldService::createCustomFieldSets()
        $customFieldNames = [
            'shipperhq_shipping_group',
            'shipperhq_warehouse',
            'ship_separately',
            'shipperhq_dim_group',
        ];
        
        // Delete each custom field
        foreach ($customFieldNames as $fieldName) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', $fieldName));
            
            $customField = $this->customFieldRepository->search($criteria, $context)->first();
            
            if ($customField) {
                $this->customFieldRepository->delete([
                    ['id' => $customField->getId()]
                ], $context);
            }
        }
        
        // Remove the custom field set
        // Name is defined in CustomFieldService::createCustomFieldSets()
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'shipperhq_product'));
        
        $customFieldSet = $this->customFieldSetRepository->search($criteria, $context)->first();
        
        if ($customFieldSet) {
            $this->customFieldSetRepository->delete([
                ['id' => $customFieldSet->getId()]
            ], $context);
        }
    }

    private function removeConfiguration(): void
    {
        // Remove all configuration with the plugin prefix
        $configPrefix = 'SHQRateProvider.config.';
        
        // Delete from system_config table
        $this->connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key LIKE :configPrefix',
            ['configPrefix' => $configPrefix . '%']
        );
    }
}
