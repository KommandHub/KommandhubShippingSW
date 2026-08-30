import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';
import frFR from './snippet/fr-FR.json';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('fr-FR', frFR);

import './acl';
import './component/kommandhub-shipping-carrier-select';
import './component/kommandhub-shipping-provider-select';
import CarrierApiService from './service/carrier-api.service';

// Carrier catalogue API service, consumed by the allowed-carriers config field.
Shopware.Application.addServiceProvider('carrierApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new CarrierApiService(initContainer.httpClient, container.loginService);
});
