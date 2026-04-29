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

namespace SHQ\RateProvider\Feature\ConfigurationHandler\Controller;

use Psr\Log\LoggerInterface;
use SHQ\RateProvider\Feature\ConfigurationHandler\UseCase\RefreshShippingMethodsUseCase;
use SHQ\RateProvider\Feature\ConfigurationHandler\UseCase\TestConnectionUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class ApiConnectorController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TestConnectionUseCase $testConnection,
        private readonly RefreshShippingMethodsUseCase $refreshMethods,
    ) {}

    #[Route(
        path: '/api/_action/shq-api-test/test-connection',
        name: 'api.action.shq-api-test.test-connection',
        methods: ['POST'],
        defaults: ['_acl' => ['system_config:read']],
    )]
    public function testConnection(): JsonResponse
    {
        $this->logger->info('SHIPPERHQ: testConnection requested');
        $this->testConnection->execute();

        return new JsonResponse(['success' => true]);
    }

    #[Route(
        path: '/api/_action/shq-api-test/refresh-methods',
        name: 'api.action.shq-api-test.refresh-methods',
        methods: ['POST'],
        defaults: ['_acl' => ['system_config:update']],
    )]
    public function refreshMethods(): JsonResponse
    {
        $this->logger->info('SHIPPERHQ: refreshMethods requested');
        $methods = $this->refreshMethods->execute();

        return new JsonResponse(['success' => true, 'methods' => $methods]);
    }
}
