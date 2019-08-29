define(
    [
        'ko',
        'Heidelpay_MGW/js/view/payment/method-renderer/base'
    ],
    function (ko, Component) {
        'use strict';

        return Component.extend({
            redirectUrl: 'checkout/onepage/success',

            defaults: {
                template: 'Heidelpay_MGW/payment/invoice_factoring'
            },

            initializeForm: function () {
                this.initializeCustomerForm(
                    'invoice-factoring-customer',
                    'invoice-factoring-customer-error'
                );
                this.resourceProvider = this.sdk.InvoiceFactoring();
            },

            allInputsValid: function () {
                return this.customerValid;
            },

            validate: function () {
                return this.allInputsValid()();
            },
        });
    }
);