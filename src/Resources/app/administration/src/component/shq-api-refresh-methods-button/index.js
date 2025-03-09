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
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            if (!this.pluginConfig["SHQRateProvider.config.apiKey"] || !this.pluginConfig["SHQRateProvider.config.authenticationCode"]) {
                this.createNotificationError({
                    title: this.$tc('shqApiTestButton.error'),
                    message: this.$tc('shqApiTestButton.missingCredentials')
                });
                return;
            }


            this.isLoading = true;
            this.ShipperHQApiService.refreshMethods({
                apiKey: this.pluginConfig["SHQRateProvider.config.apiKey"],
                authenticationCode: this.pluginConfig["SHQRateProvider.config.authenticationCode"]
            }).then((res) => {
                if (res.success) {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title: this.$tc('shqApiRefreshMethodsButton.success'),
                        message: this.$tc('shqApiRefreshMethodsButton.successMessage')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('shqApiRefreshMethodsButton.error'),
                        message: this.$tc('shqApiRefreshMethodsButton.errorMessage')
                    });
                }
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('shqApiRefreshMethodsButton.error'),
                    message: error.message || this.$tc('shqApiRefreshMethodsButton.errorMessage')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
})