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

namespace SHQ\RateProvider\Handlers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class DatabaseHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $customFieldSetRepository,
    ) {}

    public function removeShipperHQTables(Context $context): void
    {
        // Remove custom field set (cascade-deletes associated custom fields)
        $this->removeCustomFieldSet($context);

        // Remove system configuration
        $this->removeConfiguration();
    }

    private function removeCustomFieldSet(Context $context): void
    {
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
