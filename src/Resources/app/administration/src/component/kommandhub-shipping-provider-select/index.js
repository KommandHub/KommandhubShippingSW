import template from './kommandhub-shipping-provider-select.html.twig';

const { Component } = Shopware;

/**
 * Config field: active shipping provider, populated from the installed provider
 * registry (admin API) instead of a hardcoded option list — so core never names
 * a provider and a new provider plugin appears here automatically. Stored under
 * the config key "activeProvider".
 */
Component.register('kommandhub-shipping-provider-select', {
    template,

    inject: ['carrierApiService'],

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            providers: [],
            isLoading: false,
        };
    },

    computed: {
        selected: {
            get() {
                return this.value;
            },
            set(key) {
                this.$emit('update:value', key);
            },
        },

        options() {
            return this.providers.map((provider) => ({
                value: provider.key,
                label: provider.key,
            }));
        },
    },

    created() {
        this.loadProviders();
    },

    methods: {
        async loadProviders() {
            this.isLoading = true;
            try {
                const response = await this.carrierApiService.getProviders();
                this.providers = response.providers ?? [];
            } catch (error) {
                this.providers = [];
            } finally {
                this.isLoading = false;
            }
        },
    },
});
