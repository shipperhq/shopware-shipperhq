/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package SHQ\RateProvider
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

import template from './shq-api-refresh-methods-button.html.twig';


Shopware.Component.register('shq-api-refresh-methods-button', {
    template,

    props: ['label'],

    inject: ['ShipperHQApiService'],

    mixins: [
        Shopware.Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false
        };
    },

    computed: {
        pluginConfig() {
            let $parent = this.$parent;

            while ($parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }

            return $parent.actualConfigData.null;
        }
    },

    methods: {
        async refreshMethods() {
            try {
                // Check for API key
                if (!this.pluginConfig["SHQRateProvider.config.apiKey"] || !this.pluginConfig["SHQRateProvider.config.authenticationCode"]) {
                    this.createNotificationError({
                        title: this.$tc('shqApiRefreshMethodsButton.error'),
                        message: this.$tc('shqApiRefreshMethodsButton.missingCredentials')
                    });
                    return;
                }

                // Start loading
                this.isLoading = true;
                this.processSuccess = false;

                // Make API call
                await this.ShipperHQApiService.refreshMethods();

                this.processSuccess = true;
                this.createNotificationSuccess({
                    title: this.$tc('shqApiRefreshMethodsButton.success'),
                    message: this.$tc('shqApiRefreshMethodsButton.successMessage')
                });
            } catch (error) {
                this.processSuccess = false;
                const detail = error?.response?.data?.errors?.[0]?.detail;
                this.createNotificationError({
                    title: this.$tc('shqApiRefreshMethodsButton.error'),
                    message: detail || this.$tc('shqApiRefreshMethodsButton.errorMessage')
                });
            } finally {
                this.isLoading = false;
                // Reset process success after a delay
                setTimeout(() => {
                    this.processSuccess = false;
                }, 2500);
            }
        }
    }
})
