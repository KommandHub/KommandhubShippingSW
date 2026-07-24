const PluginManager = window.PluginManager;

// Register storefront plugins against a data attribute, lazily imported so the
// bundle only loads on pages that actually use it.
//
// PluginManager.register(
//     'ShippingExample',
//     () => import('./example-plugin/example.plugin'),
//     '[data-shipping-example]'
// );
