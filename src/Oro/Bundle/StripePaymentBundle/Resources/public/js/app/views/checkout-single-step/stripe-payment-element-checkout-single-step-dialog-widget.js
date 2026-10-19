import StripePaymentElementDialogWidget from 'orostripepayment/js/app/views/stripe-payment-element-dialog-widget';
import _ from 'underscore';
import __ from 'orotranslation/js/translator';

const StripePaymentElementCheckoutSingleStepDialogWidget = StripePaymentElementDialogWidget.extend({
    /**
     * @inheritDoc
     */
    options: _.extend({}, StripePaymentElementDialogWidget.prototype.options, {
        title: __('oro.stripe_payment.single_step_checkout.payment_dialog.title')
    }),

    /**
     * @inheritDoc
     */
    constructor: function StripePaymentElementCheckoutSingleStepDialogWidget(options) {
        StripePaymentElementCheckoutSingleStepDialogWidget.__super__.constructor.call(this, options);
    }
});

export default StripePaymentElementCheckoutSingleStepDialogWidget;
