/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package SHQ\RateProvider
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

// Import snippets first
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
// Import services
import ShipperHQApiService from './service/shq-api-connector.service';
// Import components
import './component/shq-api-test-button';
import './component/shq-api-refresh-methods-button';
import './extension/sw-order-detail-details';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Shopware.Service().register('ShipperHQApiService', () => {
    return new ShipperHQApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

