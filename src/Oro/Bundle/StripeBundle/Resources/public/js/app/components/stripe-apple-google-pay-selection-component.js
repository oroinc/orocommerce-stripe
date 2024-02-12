import _ from 'underscore';
import $ from 'jquery';
import BaseComponent from 'oroui/js/app/components/base/component';
import stripeClient from 'orostripe/js/app/components/stripe-client';
import mediator from 'oroui/js/mediator';

const StripeAppleGooglePaySelectionComponent = BaseComponent.extend({
    defaults: {
        selector: {
            sourceElementSelector: '#stripe-apple-google-pay-element',
            paymentMethodsContainerSelector: '[data-content="payment_method_form"]', // Container for all payment methods radio buttons.
            paymentRadioElementSelector: '[data-item-container]', // All payment methods radio buttons.
            methodLabelSelector: 'label[data-radio]', // Stripe Google/Apple pay label.
            paymentMethodRadioInput: 'input[type="radio"]'
        },
        country: 'US'
    },

    /**
     * @param {Object} options
     */
    constructor: function StripeAppleGooglePaySelectionComponent(options) {
        StripeAppleGooglePaySelectionComponent.__super__.constructor.call(this, options);
    },

    /**
     * @param {Object} options
     */
    initialize(options) {
        StripeAppleGooglePaySelectionComponent.__super__.initialize.call(this, options);
        this.options = Object.assign({}, this.defaults, options);
        this.options.selector = Object.assign({}, this.defaults.selector, options.selector);
        // We could not use options._sourceElement because payment-method-selector-component component responsible
        // for methods rendering does not dispose when customer move to the next step. As a result event listeners
        // are executed when user navigates through steps and rebuild payment step layout structure.
        this.$el = $(this.options.selector.sourceElementSelector);
        this.paymentMethodsContainer = this.getPaymentMethodsContainer();
        this.paymentElement = this.getPaymentElement();

        this.initializePaymentOption();
    },

    /**
     * Check if Stripe Apple/Google Pay is available on the site.
     */
    initializePaymentOption() {
        // Absence of this.paymentElement means payment options were already initialized
        // and Apple/Google Pay was removed because it was unavailable.
        // This condition is reached when user returns to the payment method step from other checkout steps
        if (this.paymentElement.length === 0) {
            this.initializeWithoutAppleGooglePay();

            return;
        }

        const stripe = stripeClient.getStripeInstance(this.options);

        // This amount isn't used anywhere and is only required at this point to check
        // if Apple Pay or Google Pay are available
        const paymentRequest = stripe.paymentRequest({
            country: this.options.country,
            currency: this.options.totals.total.currency.toLowerCase(),
            total: {
                label: 'Total',
                amount: 100
            }
        });

        paymentRequest.canMakePayment().then(result => {
            if (result !== null &&
                result.hasOwnProperty('applePay') &&
                result.applePay === true
            ) {
                // init as Apple Pay
                this.changePaymentOptionLabel('stripe-apple-pay-item');
                this.showPaymentOption();

                return;
            }

            if (result !== null &&
                result.hasOwnProperty('googlePay') &&
                result.googlePay === true
            ) {
                // init as Google Pay
                this.changePaymentOptionLabel('stripe-google-pay-item');
                this.showPaymentOption();

                return;
            }

            this.removePaymentOption();
            this.selectNextDefaultPaymentMethod();
        });
    },

    initializeWithoutAppleGooglePay() {
        this.refreshSelectedPaymentOptionFromFormField();
    },

    /**
     * Show Stripe Apple/Google Pay payment method which is not displayed by default.
     */
    showPaymentOption() {
        this.paymentElement.removeClass('hidden');

        mediator.trigger('oro-stripe:google-apple-pay-method:initialized');
    },

    /**
     * Remove Stripe Apple/Google Pay payment option if it is not available on the site.
     */
    removePaymentOption() {
        this.paymentElement.remove();
    },

    /**
     * Selects the next payment method by default after Apple/Google Pay method was removed
     */
    selectNextDefaultPaymentMethod() {
        const firstRadioInput = this.paymentMethodsContainer.find(this.options.selector.paymentMethodRadioInput)[0];
        if (_.isUndefined(firstRadioInput)) {
            return;
        }

        $(firstRadioInput).attr('checked', 'checked')
            .trigger('click')
            .change();
    },

    refreshSelectedPaymentOptionFromFormField() {
        const paymentMethodValue = $('[name="oro_workflow_transition[payment_method]"]').val();
        if (_.isUndefined(paymentMethodValue)) {
            return;
        }

        const paymentMethodToSelect = this.paymentMethodsContainer.find(this.options.selector.paymentMethodRadioInput)
            .filter(
                '[value="' + paymentMethodValue + '"]'
            );

        // In case payment method from the form was either not found or it was Apple/Google Pay,
        // but it was unavailable and we need to select next payment method
        if (_.isUndefined(paymentMethodToSelect) || paymentMethodToSelect.length === 0) {
            this.selectNextDefaultPaymentMethod();
        }

        paymentMethodToSelect.attr('checked', 'checked')
            .trigger('click')
            .change();
    },

    /**
     * Replace label.
     *
     * @param paymentViewElementId
     */
    changePaymentOptionLabel(paymentViewElementId) {
        // Replace only the text part of the label, as it also contains radio button input inside the label
        const label = $('#' + paymentViewElementId);
        $(this.paymentElement).find(this.options.selector.methodLabelSelector).contents().last().remove();
        label.clone().appendTo(
            $(this.paymentElement).find(this.options.selector.methodLabelSelector)
        );
    },

    /**
     * @returns {jQuery|HTMLElement|null|any|Element}
     */
    getPaymentMethodsContainer() {
        return $(this.options.selector.paymentMethodsContainerSelector);
    },

    /**
     * @returns {jQuery|HTMLElement|null|any|Element}
     */
    getPaymentElement() {
        return this.$el.closest(this.options.selector.paymentRadioElementSelector);
    }
});

export default StripeAppleGooglePaySelectionComponent;
