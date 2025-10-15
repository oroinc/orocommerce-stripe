import $ from 'jquery';
import BaseView from 'oroui/js/app/views/base/view';

/**
 * Uses to change position of this element on the page, moving it under Stripe Payment method container. Actual for
 * single page checkout flow.
 */
const StripeCardView = BaseView.extend({
    defaults: {
        stripePaymentContainer: '#stripe-card-element'
    },

    listen: {
        'oro-stripe:payment-element-mounted mediator': 'adjustElement',
        'oro-stripe:google-apple-pay-method:initialized mediator': 'adjustElement',
        'layout:reposition mediator': 'adjustElement',
        'layout:content-relocated mediator': 'adjustElement',
        'layout:adjustHeight mediator': 'adjustElement',
        'checkout:shipping-method:rendered mediator': 'adjustElement',
        'sticky-panel:toggle-state mediator': 'adjustElement',
        'single-page-checkout:after-change mediator': 'adjustElement',
        'checkout:payment:method:changed mediator': 'updateSelectedPaymentMethod'
    },

    selectedPaymentMethod: null,

    /**
     * @inheritdoc
     */
    constructor: function StripeCardView(options) {
        StripeCardView.__super__.constructor.call(this, options);
    },

    /**
     * @inheritdoc
     */
    initialize(options) {
        StripeCardView.__super__.initialize.call(this, options);
        this.options = Object.assign({}, this.defaults, options);
        this.selectedPaymentMethod = this.options.currentPaymentMethod || null;
        this.adjustElement();
    },

    /**
     * Apply offset position to display block inside stripe payment method container.
     */
    adjustElement() {
        if (this.selectedPaymentMethod === this.options.paymentMethod) {
            this.$el.addClass('stripe-payment-tmp-container__mounted');
        } else {
            this.$el.removeClass('stripe-payment-tmp-container__mounted');
        }

        if (!this.$el.children().length) {
            return;
        }

        const $stripePaymentContainer = $(this.getStripePaymentSelector());
        this.$el.offset($stripePaymentContainer.offset());
        this.$el.width($stripePaymentContainer.width());
    },

    getStripePaymentSelector() {
        return this.options.stripePaymentContainer;
    },

    /**
     * Update selected payment method and adjust element
     *
     * @param data
     */
    updateSelectedPaymentMethod(data) {
        this.selectedPaymentMethod = data.paymentMethod;
    }
});

export default StripeCardView;
