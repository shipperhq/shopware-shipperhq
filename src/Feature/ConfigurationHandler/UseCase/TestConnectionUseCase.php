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

namespace SHQ\RateProvider\Feature\ConfigurationHandler\UseCase;

use Psr\Log\LoggerInterface;
use SHQ\RateProvider\Exception\ShipperHQException;
use SHQ\RateProvider\Service\ShipperHQClient;

class TestConnectionUseCase
{
    public function __construct(
        private readonly ShipperHQClient $client,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws ShipperHQException
     */
    public function execute(): void
    {
        try {
            $allowedMethods = $this->client->getAllowedMethods();
        } catch (ShipperHQException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('ShipperHQ connection test failed', ['exception' => $e]);
            throw ShipperHQException::connectionTestFailed($e);
        }

        if (empty($allowedMethods)) {
            throw ShipperHQException::noMethodsReturned();
        }
    }
}
