define(
    [
        'Heidelpay_MGW/js/view/payment/method-renderer/base'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Heidelpay_MGW/payment/paypal'
            },

            initializeForm: function () {
                this.resourceProvider = this.sdk.Paypal();
            },
        });
    }
);