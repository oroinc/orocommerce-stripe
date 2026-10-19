import DialogWidget from 'oro/dialog-widget';
import _ from 'underscore';

const StripePaymentElementDialogWidget = DialogWidget.extend({
    options: _.extend({}, DialogWidget.prototype.options, {
        stateEnabled: false,
        incrementalPosition: false,
        dialogOptions: {
            modal: true,
            resizable: false,
            autoResize: true,
            allowMaximize: false
        }
    }),

    listen: {
        widgetReady: 'repositionOnContentResize'
    },

    contentResizeObserver: null,

    constructor: function StripePaymentElementDialogWidget(options) {
        StripePaymentElementDialogWidget.__super__.constructor.call(this, options);
    },

    initialize(options) {
        const instanceDialogOptions = {...this.options.dialogOptions};

        StripePaymentElementDialogWidget.__super__.initialize.call(this, {
            dialogOptions: instanceDialogOptions,
            ...options
        });
    },

    repositionOnContentResize() {
        this.contentResizeObserver?.disconnect();
        this.contentResizeObserver = new ResizeObserver(() => this.resetDialogPosition());
        this.contentResizeObserver.observe(this.el);
    },

    dispose() {
        if (this.disposed) {
            return;
        }

        this.contentResizeObserver?.disconnect();
        delete this.contentResizeObserver;

        StripePaymentElementDialogWidget.__super__.dispose.call(this);
    },

    _onAdoptedFormSubmitClick() {}
});

export default StripePaymentElementDialogWidget;
