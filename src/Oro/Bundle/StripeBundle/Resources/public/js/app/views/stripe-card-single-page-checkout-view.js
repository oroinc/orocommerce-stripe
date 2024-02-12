define(function(require) {
    'use strict';

    const $ = require('jquery');
    const BaseView = require('oroui/js/app/views/base/view');

    /**
     * Uses to change position of this element on the page, moving it under Stripe Payment method container. Actual for
     * single page checkout flow.
     */
    const StripeCardView = BaseView.extend({
        defaults: {
            stripePaymentContainer: '#stripe-card-element'
        },

        listen: {
            'oro-stripe:payment-element-mounted mediator': 'onCardMount',
            'oro-stripe:google-apple-pay-method:initialized mediator': 'adjustElement',
            'layout:reposition mediator': 'adjustElement',
            'layout:content-relocated mediator': 'adjustElement',
            'layout:adjustHeight mediator': 'adjustElement',
            'checkout:shipping-method:rendered mediator': 'adjustElement',
            'sticky-panel:toggle-state mediator': 'adjustElement',
            'single-page-checkout:after-change mediator': 'adjustElement'
        },

        /**
         * @inheritdoc
         */
        constructor: function StripeCardView(options) {
            StripeCardView.__super__.constructor.call(this, options);
        },

        /**
         * @inheritdoc
         */
        initialize: function(options) {
            StripeCardView.__super__.initialize.call(this, options);
            this.options = Object.assign({}, this.defaults, options);
            this.adjustElement();
        },

        /**
         * Apply position changes when card element mounted into DOM structure.
         */
        onCardMount: function() {
            this.adjustElement();
            this.$el.addClass('stripe-payment-tmp-container__mounted');
        },

        /**
         * Apply offset position to display block inside stripe payment method container.
         */
        adjustElement: function() {
            if (!this.$el.children().length) {
                return;
            }
            const $stripePaymentContainer = $(this.options.stripePaymentContainer);
            this.$el.offset($stripePaymentContainer.offset());
            this.$el.width($stripePaymentContainer.width());
        }
    });

    return StripeCardView;
});
