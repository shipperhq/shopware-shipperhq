<?php

namespace SHQ\RateProvider\Feature\ConfigurationHandler\UseCase;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

class TestConnectionUseCase
{
    public function __construct() {}

    public function execute(RequestDataBag $dataBag): array
    {
        $apiKey = $dataBag->get('SHQRateProvider.config.apiKey');

        // // Optionally validate the key here or pass to domain service
        // if ($apiKey && $this->handler->validateApiKey($apiKey)) {
        //     return ['success' => true];
        // }

        // return ['success' => false, 'error' => 'Invalid API Key'];
        return ['success' => true];
    }
}
