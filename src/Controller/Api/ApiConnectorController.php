<?php

namespace SHQ\RateProvider\Controller\Api;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use SHQ\RateProvider\Handlers\ShipperHQHandler;

#[Route(defaults: ['_routeScope' => ['api']])]
class ApiConnectorController
{
    private LoggerInterface $logger;
    private ShipperHQHandler $shipperHQHandler;

    public function __construct(
        LoggerInterface $logger,
        ShipperHQHandler $shipperHQHandler
    ) {
        $this->logger = $logger;
        $this->shipperHQHandler = $shipperHQHandler;
    }

    #[Route(path: '/api/_action/shq-api-test/test-connection', name: 'api.action.shq-api-test.test-connection', methods: ['POST'])]
    public function testConnection(RequestDataBag $dataBag): JsonResponse
    {   
        $this->logger->info('SHIPPERHQ: testConnection in controller', ['data' => $dataBag->all()]);
        return new JsonResponse($this->checkCredentials($dataBag));
    }


    /**
     * Entry point for reloading the shipping methods from ShipperHQ
     */
    #[Route(path: '/api/_action/shq-api-test/refresh-methods', name: 'api.action.shq-api-test.refresh-methods', methods: ['POST'])]
    public function refreshMethods(RequestDataBag $dataBag): JsonResponse
    {   
        $this->logger->info('SHIPPERHQ: refreshMethods in controller', ['data' => $dataBag->all()]);
        return new JsonResponse($this->reloadShippingMethods($dataBag));
    }


    public function checkCredentials(RequestDataBag $dataBag): array
    {
        $apiKey = $dataBag->get('SHQRateProvider.config.apiKey');
        $success = ['success' => true];

        // Write your code here


        // TODO JB Add code here to test this connection


        return $success;
    }


    /**
     * Reloads the shipping methods from ShipperHQ
     */
    public function reloadShippingMethods(RequestDataBag $dataBag): array
    {
       
        $success = ['success' => false];

        if ($this->shipperHQHandler->reloadShippingMethods($dataBag)) {
            $success['success'] = true;
        }

        return $success;

    }
}