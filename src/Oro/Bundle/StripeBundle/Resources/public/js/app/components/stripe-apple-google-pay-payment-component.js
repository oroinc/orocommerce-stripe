import _ from 'underscore';
import $ from 'jquery';
import numeral from 'numeral';
import mediator from 'oroui/js/mediator';
import stripeClient from 'orostripe/js/app/components/stripe-client';
import StripePaymentComponent from 'orostripe/js/app/components/stripe-payment-component';

const StripeAppleGooglePayPaymentComponent = StripePaymentComponent.extend({
    defaults: {
        selector: {
            checkoutButtonSelector: '.checkout-form__submit[type="submit"]',
            paymentMethodSelector: '[name$="[payment_method]"]'
        },
        country: 'US'
    },

    stripe: null,
    paymentRequest: null,
    stripePaymentShown: false,
    form: null,
    zeroDecimalCurrencies: [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'
    ],
    threeDecimalCurrencies: ['BHD', 'JOD', 'KWD', 'OMR', 'TND'],

    /**
     * @inheritdoc
     */
    constructor: function StripeAppleGooglePayPaymentComponent(options) {
        StripeAppleGooglePayPaymentComponent.__super__.constructor.call(this, options);
    },

    /**
     * @inheritdoc
     */
    initialize(options) {
        this.options = _.extend(this.defaults, options || {});
        StripeAppleGooglePayPaymentComponent.__super__.initialize.call(this, this.options);

        this.initStripePaymentRequest();
    },

    convertToStripeFormat(value, currency) {
        // https://docs.stripe.com/currencies#zero-decimal
        if (this.zeroDecimalCurrencies.includes(currency)) {
            return Math.round(numeral(value.toFixed(0)).value());
        }
        // https://docs.stripe.com/currencies#three-decimal
        if (this.threeDecimalCurrencies.includes(currency)) {
            return Math.round(numeral(value.toFixed(2)).multiply(1000).value());
        }
        // https://docs.stripe.com/currencies#presentment-currencies
        return Math.round(numeral(value.toFixed(2)).multiply(100).value());
    },

    getForm($element) {
        return $element.prop('form') ? $($element.prop('form')) : $element.closest('form');
    },

    /**
     * Initialize event listener on checkout form submit to display Google/Apple Pay dialog window.
     * Initialize event listener on event when customer submits Google/Apple Pay dialog form.
     */
    initStripePaymentRequest() {
        const checkoutButton = $(this.options.selector.checkoutButtonSelector);
        this.form = this.getForm(checkoutButton);

        const totals = this.options.totals;
        const subtotals = this.getStripePaymentRequestSubtotals();

        this.stripe = stripeClient.getStripeInstance(this.options);
        this.paymentRequest = this.stripe.paymentRequest({
            country: this.options.country,
            currency: totals.total.currency.toLowerCase(),
            total: {
                label: totals.total.label,
                amount: this.convertToStripeFormat(
                    totals.total.signedAmount,
                    totals.total.currency
                )
            },
            displayItems: subtotals
        });

        this.paymentRequest.canMakePayment().then(result => {
            if (result !== null && (result.applePay || result.googlePay)) {
                mediator.on('checkout:before-submit', this.submitOrderHandler, this);
            }
        });

        this.paymentRequest.on('paymentmethod', event => {
            const additionalData = {
                stripePaymentMethodId: event.paymentMethod.id
            };
            mediator.trigger('checkout:payment:additional-data:set', JSON.stringify(additionalData));
            event.complete('success');
            // Set flag as true only when customer user submitted Google/Apple pay dialog window form.
            this.stripePaymentShown = true;

            this.form.trigger('submit');
        });
    },

    /**
     * Check if selected payment method is Stripe Google/Apple Pay payment method.
     *
     * @returns {boolean}
     */
    isApplicable(eventData) {
        let paymentMethod = eventData.hasOwnProperty('data') && eventData.data.hasOwnProperty('paymentMethod')
            ? eventData.data.paymentMethod
            : null;

        if (null === paymentMethod) {
            // In case of the multi page checkout, payment method field isn't available on the submit order step,
            // but this component is only loaded if the appropriate payment method is selected
            if (this.form.find(this.options.selector.paymentMethodSelector).length === 0) {
                return true;
            }

            // In case of the single page checkout and if payment method is absent in event data
            // it can be obtained from the form field.
            paymentMethod = this.form.find(this.options.selector.paymentMethodSelector).val();
        }

        return paymentMethod === this.options.paymentMethod;
    },

    /**
     * Build totals.
     *
     * @returns {[]}
     */
    getStripePaymentRequestSubtotals() {
        const subtotals = [];

        _.each(this.options.totals.subtotals, function(item) {
            if (item.signedAmount === 0) {
                // return in 'each' in Underscore means continue :)
                return;
            }

            subtotals.push({
                label: item.label,
                amount: this.convertToStripeFormat(item.signedAmount, item.currency)
            });
        }, this);

        return subtotals;
    },

    /**
     * Show Apple/Google Pay dialog window for card selection.
     *
     * @param eventData
     */
    submitOrderHandler(eventData) {
        if (!this.isApplicable(eventData)) {
            return;
        }

        if (this.stripePaymentShown === true) {
            return;
        }

        eventData.stopped = true;
        if (!_.isUndefined(eventData.event)) {
            eventData.event.preventDefault();
        }

        this.paymentRequest.show();
    },

    /**
     * Unbind global event handlers
     */
    dispose() {
        if (this.disposed) {
            return;
        }

        mediator.off('checkout:before-submit', this.submitOrderHandler);
        StripeAppleGooglePayPaymentComponent.__super__.dispose.call(this);
    }
});

export default StripeAppleGooglePayPaymentComponent;
