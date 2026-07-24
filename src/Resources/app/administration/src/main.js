import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';
import frFR from './snippet/fr-FR.json';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('fr-FR', frFR);

import './acl';

// Register modules, views and services below, one import per directory, e.g.
//   import './module/sw-order/page/sw-order-detail';
//   Shopware.Service().register('shippingService', container => { ... });
