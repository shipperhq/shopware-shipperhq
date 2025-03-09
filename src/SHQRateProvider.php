<?php declare(strict_types=1);

namespace SHQ\RateProvider;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class SHQRateProvider extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        if (!$uninstallContext->keepUserData()) {
            $this->removeAllShipperHQTables();
        }
    }

      /**
     * @return void
     * @throws Exception
     */
    private function removeAllShipperHQTables(): void
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        $databaseHandler = new DatabaseHandler($connection);
        $databaseHandler->removeShipperHQTables();
    }
}