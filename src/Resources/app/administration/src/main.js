// Import snippets first
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

// Import services
import ShipperHQApiService from './service/shq-api-connector.service';

Shopware.Service().register('ShipperHQApiService', () => {
    return new ShipperHQApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

// Import components
import './component/shq-api-test-button';
import './component/shq-api-refresh-methods-button';

const { Component } = Shopware;

// Completely hide the shipping section by rendering nothing
Component.override('sw-order-delivery-detail-shipping', {
    template: '<!-- Shipping section hidden by SHQRateProvider -->'
});
