/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package ShipperHQ_Calendar
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
                console.log('in refreshMethods in index.js');
                

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
                const response = await this.ShipperHQApiService.refreshMethods({
                    apiKey: this.pluginConfig["SHQRateProvider.config.apiKey"],
                    authenticationCode: this.pluginConfig["SHQRateProvider.config.authenticationCode"]
                });

                // Handle response
                if (response && response.success) {
                    this.processSuccess = true;
                    this.createNotificationSuccess({
                        title: this.$tc('shqApiRefreshMethodsButton.success'),
                        message: this.$tc('shqApiRefreshMethodsButton.successMessage')
                    });
                } else {
                    throw new Error(response.error || 'Unknown error occurred');
                }
            } catch (error) {
                console.error('Error in refresh methods:', error);
                this.processSuccess = false;
                this.createNotificationError({
                    title: this.$tc('shqApiRefreshMethodsButton.error'),
                    message: error.message || this.$tc('shqApiRefreshMethodsButton.errorMessage')
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
