import StripePaymentElementDialogWidget from 'orostripepayment/js/app/views/stripe-payment-element-dialog-widget';
import _ from 'underscore';
import __ from 'orotranslation/js/translator';

const StripePaymentElementCheckoutMultiStepDialogWidget = StripePaymentElementDialogWidget.extend({
    /**
     * @inheritDoc
     */
    options: _.extend({}, StripePaymentElementDialogWidget.prototype.options, {
        title: __('oro.stripe_payment.multi_step_checkout.payment_dialog.title')
    }),

    /**
     * @inheritDoc
     */
    constructor: function StripePaymentElementCheckoutMultiStepDialogWidget(options) {
        StripePaymentElementCheckoutMultiStepDialogWidget.__super__.constructor.call(this, options);
    }
});

export default StripePaymentElementCheckoutMultiStepDialogWidget;
