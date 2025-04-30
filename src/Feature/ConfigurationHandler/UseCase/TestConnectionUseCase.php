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
