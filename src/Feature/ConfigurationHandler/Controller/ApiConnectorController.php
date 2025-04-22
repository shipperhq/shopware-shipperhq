<?php

namespace SHQ\RateProvider\Feature\ConfigurationHandler\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use SHQ\RateProvider\Feature\ConfigurationHandler\UseCase\TestConnectionUseCase;
use SHQ\RateProvider\Feature\ConfigurationHandler\UseCase\RefreshShippingMethodsUseCase;

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
