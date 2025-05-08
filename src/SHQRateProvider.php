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

namespace SHQ\RateProvider;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use SHQ\RateProvider\Feature\ProductData\Service\CustomFieldService;
use SHQ\RateProvider\Handlers\DatabaseHandler;

class SHQRateProvider extends Plugin
{
    public function boot(): void
    {
        parent::boot();
        
        // Register vendor autoloader
        $vendorDir = $this->getPath() . '/vendor';
        if (file_exists($vendorDir . '/autoload.php')) {
            require_once $vendorDir . '/autoload.php';
        }
    }

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->createCustomFields($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        $this->createCustomFields($updateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if (!$uninstallContext->keepUserData()) {
          #  $this->removeAllShipperHQTables();
        }
        parent::uninstall($uninstallContext);
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function removeAllShipperHQTables(): void
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        $databaseHandler = new DatabaseHandler($connection);
        $databaseHandler->removeShipperHQTables();
    }

    private function createCustomFields(Context $context): void
    {
        $customFieldService = new CustomFieldService(
            customFieldSetRepository: $this->container->get('custom_field_set.repository')
        );
        $customFieldService->createCustomFieldSets($context);
    }
}
