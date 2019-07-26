define(
    [
        'ko',
        'Heidelpay_Gateway2/js/view/payment/method-renderer/base'
    ],
    function (ko, Component) {
        'use strict';

        return Component.extend({
            defaults: {
                config: window.checkoutConfig.payment.hpg2_creditcard,
                fields: {
                    cvc: {valid: null},
                    expiry: {valid: null},
                    number: {valid: null},
                },
                template: 'Heidelpay_Gateway2/payment/creditcard'
            },

            initializeForm: function () {
                var self = this;

                this.resourceProvider = this.heidelpay.Card();
                this.resourceProvider.create('number', {
                    containerId: 'card-element-id-number',
                    onlyIframe: false
                });
                this.resourceProvider.create('expiry', {
                    containerId: 'card-element-id-expiry',
                    onlyIframe: false
                });
                this.resourceProvider.create('cvc', {
                    containerId: 'card-element-id-cvc',
                    onlyIframe: false
                });

                this.fields.cvc.valid = ko.observable(false);
                this.fields.expiry.valid = ko.observable(false);
                this.fields.number.valid = ko.observable(false);

                this.resourceProvider.addEventListener('change', function (event) {
                    if ("type" in event) {
                        self.fields[event.type].valid("success" in event && event.success);
                    }
                });
            },

            allInputsValid: function () {
                var self = this;

                return ko.computed(function () {
                    return self.fields.cvc.valid() &&
                        self.fields.expiry.valid() &&
                        self.fields.number.valid();
                });
            },

            validate: function () {
                return this.allInputsValid()();
            },
        });
    }
);