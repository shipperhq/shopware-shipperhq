<?php
/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package ShipperHQ_Calendar
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace SHQ\RateProvider\Feature\ConfigurationHandler\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use SHQ\RateProvider\Feature\ConfigurationHandler\UseCase\RefreshShippingMethodsUseCase;
use SHQ\RateProvider\Feature\ConfigurationHandler\UseCase\TestConnectionUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class ApiConnectorController
{
    public function __construct(
        private LoggerInterface $logger,
        private TestConnectionUseCase $testConnection,
        private RefreshShippingMethodsUseCase $refreshMethods
    ) {}

    #[Route(path: '/api/_action/shq-api-test/test-connection', name: 'api.action.shq-api-test.test-connection', methods: ['POST'])]
    public function testConnection(RequestDataBag $dataBag): JsonResponse
    {
        $this->logger->info('SHIPPERHQ: testConnection', ['data' => $dataBag->all()]);
        return new JsonResponse($this->testConnection->execute($dataBag));
    }

    #[Route(path: '/api/_action/shq-api-test/refresh-methods', name: 'api.action.shq-api-test.refresh-methods', methods: ['POST'])]
    public function refreshMethods(RequestDataBag $dataBag): JsonResponse
    {
        $this->logger->info('SHIPPERHQ: refreshMethods', ['data' => $dataBag->all()]);
        return new JsonResponse($this->refreshMethods->execute($dataBag));
    }
}
