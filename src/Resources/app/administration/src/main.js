// Import snippets first
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

// Import services
import ShipperHQApiService from './service/api-test.service';

Shopware.Service().register('ShipperHQApiService', () => {
    return new ShipperHQApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});


// Import component
import './component/shq-api-test-button';