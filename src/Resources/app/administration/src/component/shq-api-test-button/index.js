import template from './shq-api-test-button.html.twig';

//const ShipperHQApiService = Shopware.Service('ShipperHQApiService');

Shopware.Component.register('shq-api-test-button', {
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
        check() {
            if (!this.pluginConfig["SHQRateProvider.config.apiKey"] || !this.pluginConfig["SHQRateProvider.config.authenticationCode"]) {
                this.createNotificationError({
                    title: this.$tc('shqApiTestButton.error'),
                    message: this.$tc('shqApiTestButton.missingCredentials')
                });
                return;
            }

            this.isLoading = true;
            this.ShipperHQApiService.testConnection({
                apiKey: this.pluginConfig["SHQRateProvider.config.apiKey"],
                authenticationCode: this.pluginConfig["SHQRateProvider.config.authenticationCode"]
            }).then((response) => {
                console.log(response);
                if (response.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('shqApiTestButton.success'),
                        message: this.$tc('shqApiTestButton.successMessage')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('shqApiTestButton.error'),
                        message: response.message || this.$tc('shqApiTestButton.errorMessage')
                    });
                }
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('shqApiTestButton.error'),
                    message: error.message || this.$tc('shqApiTestButton.errorMessage')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});