<?php

namespace SHQ\RateProvider\Handlers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DatabaseHandler
{
    private Connection $connection;
    private SystemConfigService $systemConfigService;
    private EntityRepository $systemConfigRepository;

    public function __construct(
        Connection $connection,
        SystemConfigService $systemConfigService,
        EntityRepository $systemConfigRepository
    ) {
        $this->connection = $connection;
        $this->systemConfigService = $systemConfigService;
        $this->systemConfigRepository = $systemConfigRepository;
    }

    public function removeConfiguration(Context $context): void
    {
        // Remove all configuration with the plugin prefix
        $configPrefix = 'SHQRateProvider.config.';
        
        // Delete from system_config table
        $this->connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key LIKE :configPrefix',
            ['configPrefix' => $configPrefix . '%']
        );
        
        // Clear config cache
        $this->systemConfigService->delete($configPrefix);
    }
}
