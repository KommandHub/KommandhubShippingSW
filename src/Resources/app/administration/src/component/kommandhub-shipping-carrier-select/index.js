import template from './kommandhub-shipping-carrier-select.html.twig';

const { Component } = Shopware;

/**
 * Advanced plugin-config field: fetches the selected provider's carriers and
 * lets the store owner choose which are allowed at checkout. The chosen carrier
 * codes are stored under the config key "allowedCarriers".
 *
 * Two Shopware-internal touch points to validate during the admin build:
 *  - reading the sibling "activeProvider" value + the current salesChannelId
 *    from the surrounding sw-system-config (resolveProviderContext below);
 *  - the v-model contract (`value` prop + `update:value`) the config renderer
 *    uses for custom components.
 */
Component.register('kommandhub-shipping-carrier-select', {
    template,

    inject: ['carrierApiService'],

    props: {
        value: {
            type: Array,
            required: false,
            default: () => [],
        },
    },

    data() {
        return {
            carriers: [],
            isLoading: false,
            errorMessage: null,
        };
    },

    computed: {
        selected: {
            get() {
                return this.value || [];
            },
            set(codes) {
                this.$emit('update:value', codes);
            },
        },

        options() {
            return this.carriers.map((carrier) => ({
                value: carrier.code,
                label: carrier.logoUrl ? `${carrier.name}` : carrier.name,
            }));
        },
    },

    created() {
        this.loadCarriers();
    },

    methods: {
        resolveProviderContext() {
            // Walk up to the surrounding sw-system-config to read the sibling
            // provider selection and the sales channel currently being edited.
            let node = this.$parent;
            while (node && !node.actualConfigData) {
                node = node.$parent;
            }
            if (!node) {
                return { provider: null, salesChannelId: null };
            }
            const salesChannelId = node.currentSalesChannelId ?? null;
            const scope = node.actualConfigData?.[salesChannelId] ?? node.actualConfigData?.null ?? {};
            return {
                provider: scope['KommandhubShippingSW.config.activeProvider'] ?? null,
                salesChannelId,
            };
        },

        async loadCarriers() {
            const { provider, salesChannelId } = this.resolveProviderContext();
            if (!provider) {
                this.carriers = [];
                return;
            }

            this.isLoading = true;
            this.errorMessage = null;
            try {
                const response = await this.carrierApiService.getCarriers(provider, salesChannelId);
                this.carriers = response.carriers ?? [];
            } catch (error) {
                this.errorMessage = error?.message ?? 'Failed to load carriers';
                this.carriers = [];
            } finally {
                this.isLoading = false;
            }
        },
    },
});
