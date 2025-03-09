import template from './shq-api-refresh-methods-button.html.twig';

const { Component, Mixin } = Shopware;

Component.register('shq-api-refresh-methods-button', {
    template,

    props: ['label'],
    inject: ['refreshMethods'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
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
            this.isLoading = true;
            this.refreshMethods.reload(this.pluginConfig).then((res) => {
                if (res.success) {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title: this.$tc('shqApiRefreshMethodsButton.title'),
                        message: this.$tc('shqApiRefreshMethodsButton.success')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('shqApiRefreshMethodsButton.title'),
                        message: this.$tc('shqApiRefreshMethodsButton.error')
                    });
                }

                this.isLoading = false;
            });
        }
    }
})