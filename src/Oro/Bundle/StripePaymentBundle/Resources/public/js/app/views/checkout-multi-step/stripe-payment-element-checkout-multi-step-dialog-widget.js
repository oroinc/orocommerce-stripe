import DialogWidget from 'oro/dialog-widget';
import _ from 'underscore';
import __ from 'orotranslation/js/translator';

const StripePaymentElementCheckoutMultiStepDialogWidget = DialogWidget.extend({
    /**
     * @inheritDoc
     */
    options: _.extend({}, DialogWidget.prototype.options, {
        title: __('oro.stripe_payment.multi_step_checkout.payment_dialog.title'),
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
    constructor: function StripePaymentElementCheckoutMultiStepDialogWidget(options) {
        StripePaymentElementCheckoutMultiStepDialogWidget.__super__.constructor.call(this, options);
    },

    /**
     * @inheritDoc
     */
    _onAdoptedFormSubmitClick: function(form) {
        // Do nothing.
    }
});

export default StripePaymentElementCheckoutMultiStepDialogWidget;
