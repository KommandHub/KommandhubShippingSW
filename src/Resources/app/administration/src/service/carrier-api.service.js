const ApiService = Shopware.Classes.ApiService;

/**
 * Thin client for the plugin's admin carrier endpoint. Given the provider the
 * store owner selected (and the current sales channel), returns that provider's
 * carrier catalogue so the config component can offer an allow-list.
 */
export default class CarrierApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'kommandhub-shipping') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'carrierApiService';
    }

    getCarriers(provider, salesChannelId) {
        return this.httpClient
            .get('/_action/kommandhub-shipping/carriers', {
                params: { provider, salesChannelId },
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }
}
