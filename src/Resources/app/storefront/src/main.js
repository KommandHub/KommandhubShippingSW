const PluginManager = window.PluginManager;

// Carrier picker: rendered under our gate shipping method on the checkout
// confirm page. Lists allow-listed carriers and posts the shopper's choice.
PluginManager.register(
    'KommandhubCarrierSelector',
    () => import('./carrier-selector/carrier-selector.plugin'),
    '[data-kommandhub-carrier-selector]',
);
