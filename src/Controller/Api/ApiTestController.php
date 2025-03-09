<?php

namespace SHQ\RateProvider\Controller\Api;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route(defaults: ['_routeScope' => ['api']])]
class ApiTestController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route(path: '/api/_action/shq-api-test/test-connection', name: 'api.action.shq-api-test.test-connection', methods: ['POST'])]
    public function testConnection(RequestDataBag $dataBag): JsonResponse
    {   
        $this->logger->info('testConnection', ['data' => $dataBag->all()]);
        return new JsonResponse($this->checkCredentials($dataBag));
    }

    public function checkCredentials(RequestDataBag $dataBag): array
    {
        $apiKey = $dataBag->get('SHQRateProvider.config.apiKey');
        $success = ['success' => true];

        // Write your code here


        // TODO JB Add code here to test this connection


        return $success;
    }
}