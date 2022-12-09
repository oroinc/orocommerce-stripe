define(function(require) {
    'use strict';

    const $ = require('jquery');
    const _ = require('underscore');
    const BaseComponent = require('oroui/js/app/components/base/component');

    const StripeTransportSettingsComponent = BaseComponent.extend({
        /**
         * @property {Object}
         */
        options: {
            paymentActionSelector: 'select[name$="[transport][paymentAction]"]',
            enableReAuthorizeSelector: 'input[name$="[transport][enableReAuthorize]"]',
            reAuthorizationErrorEmailSelector: 'input[name$="[transport][reAuthorizationErrorEmail]"]',
            container: '.control-group'
        },

        /**
         * @inheritdoc
         */
        constructor: function StripeTransportSettingsComponent(options) {
            StripeTransportSettingsComponent.__super__.constructor.call(this, options);
        },

        /**
         * @inheritdoc
         */
        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);
            this.$elem = options._sourceElement;

            this.paymentActionElem = $(this.$elem).find(this.options.paymentActionSelector);
            this.enableReAuthorizeElem = $(this.$elem).find(this.options.enableReAuthorizeSelector);
            this.reAuthorizationErrorEmailElem = $(this.$elem).find(this.options.reAuthorizationErrorEmailSelector);

            $(this.paymentActionElem).on('change', this.onPaymentActionChange.bind(this));
            $(this.paymentActionElem).trigger('change');
        },

        onPaymentActionChange: function() {
            const paymentActionValue = $(this.paymentActionElem).val();
            const self = this;

            if (paymentActionValue === 'manual') { // StripePaymentActionMapper::MANUAL
                $(this.enableReAuthorizeElem).closest(self.options.container).show();
                $(this.reAuthorizationErrorEmailElem).closest(self.options.container).show();
            } else if (paymentActionValue === 'automatic') { // StripePaymentActionMapper::AUTOMATIC
                $(this.enableReAuthorizeElem).closest(self.options.container).hide();
                $(this.reAuthorizationErrorEmailElem).closest(self.options.container).hide();

                $(this.enableReAuthorizeElem).prop('checked', false);
                $(this.reAuthorizationErrorEmailElem).val('');
            }
        }
    });

    return StripeTransportSettingsComponent;
});
