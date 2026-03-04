import DialogWidget from 'oro/dialog-widget';
import _ from 'underscore';
import __ from 'orotranslation/js/translator';

const StripePaymentElementCheckoutSingleStepDialogWidget = DialogWidget.extend({
    /**
     * @inheritDoc
     */
    options: _.extend({}, DialogWidget.prototype.options, {
        title: __('oro.stripe_payment.single_step_checkout.payment_dialog.title'),
        stateEnabled: false,
        incrementalPosition: false,
        dialogOptions: {
            modal: true,
            resizable: true,
            autoResize: true,
            allowMaximize: false
        }
    }),

    /**
     * @inheritDoc
     */
    constructor: function StripePaymentElementCheckoutSingleStepDialogWidget(options) {
        StripePaymentElementCheckoutSingleStepDialogWidget.__super__.constructor.call(this, options);
    },

    /**
     * @inheritDoc
     */
    _onAdoptedFormSubmitClick: function() {}
});

export default StripePaymentElementCheckoutSingleStepDialogWidget;
