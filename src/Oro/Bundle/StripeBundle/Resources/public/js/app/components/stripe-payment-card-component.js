import $ from 'jquery';
import mediator from 'oroui/js/mediator';
import BaseComponent from 'oroui/js/app/components/base/component';
import stripeClient from 'orostripe/js/app/components/stripe-client';
import errorsTemplate from 'tpl-loader!orostripe/templates/field-errors.html';
import __ from 'orotranslation/js/translator';

const StripePaymentCardComponent = BaseComponent.extend({
    defaults: {
        stripePaymentContainer: '#stripe-card-element',
        validationErrorContainerId: '#stripe-card-validation-container',
        cardContainer: '#stripe-payment-tmp-container',
        cardElementStyle: {},
        hidePostalCode: false,
        hideIcon: false,
        iconStyle: 'solid'
    },

    elementType: 'card',
    cardElement: null,
    cardValidationContainer: null,
    errorsContainer: null,
    listen: {
        'checkout:payment:before-transit mediator': 'beforeTransit',
        'checkout:payment:method:changed mediator': 'paymentMethodChanged'
    },
    messages: {
        card_data_incorrect: 'oro.stripe.error_messages.card_data_incorrect',
        create_payment_method_failed: 'oro.stripe.error_messages.create_payment_method_failed',
        error_handle_payment_card: 'oro.stripe.error_messages.error_handle_payment_card'
    },

    /**
     * @param {Object} options
     */
    constructor: function StripePaymentCardComponent(options) {
        StripePaymentCardComponent.__super__.constructor.call(this, options);
    },

    /**
     * @param {Object} options
     */
    initialize(options) {
        StripePaymentCardComponent.__super__.initialize.call(this, options);
        this.options = Object.assign({}, this.defaults, options);
        this.$el = $(options._sourceElement);
        this.sourceElementId = this.$el.attr('id');

        // Try to initialize card element.
        this.initCardElement();
    },

    /**
     * Initialize basic card elements using Stripe standard library tools, to be able to send securely payment card
     * data to the Stripe service.
     */
    initCardElement() {
        let cardElement = this.getPaymentElement();
        if (cardElement === null) {
            const elements = stripeClient.getStripeElements(this.elementType, this.options);
            cardElement = elements.create(this.elementType, {
                hidePostalCode: this.options.hidePostalCode,
                style: this.options.cardElementStyle,
                iconStyle: this.options.iconStyle,
                hideIcon: this.options.hideIcon
            });
        }

        // Display card element if payment method selected.
        if (this.paymentMethodIsSelected()) {
            this.mountCardElement(cardElement);
            this.stripeCardViewShow();
        }

        // Setup container for validation messages.
        this.cardValidationContainer = this.$el.find(this.options.validationErrorContainerId);

        /**
         * Set validation handler on 'change' event according to Stripe library recommendations.
         * @see https://stripe.com/docs/js/element/input_validation
         */
        cardElement.on('change', this.handleCardValidation.bind(this));
    },

    /**
     * Check if payment method selected using selectedPaymentMethod option value or check if method radiobutton is
     * checked (covers case when selectedPaymentMethod is null).
     *
     * @returns {boolean}
     */
    paymentMethodIsSelected() {
        if (this.options.selectedPaymentMethod === this.options.paymentMethod) {
            return true;
        }

        const paymentRadioButton = $('input[data-choice="' + this.options.paymentMethod + '"]');
        if (paymentRadioButton.length) {
            return paymentRadioButton.is(':checked');
        }

        return false;
    },

    /**
     * Display (attach to DOM structure) card element in specified block (container).
     *
     * @param cardElement
     */
    mountCardElement(cardElement) {
        if (!cardElement._isMounted()) {
            const container = this.resolveContainer();
            cardElement.mount(container);

            mediator.trigger('oro-stripe:payment-element-mounted');
        }
    },

    /**
     * Send request with payment card info to the Stripe service and retrieve paymentMethod object for further usage
     * in order to create paymentIntent during place order execution.
     *
     * @param {Object} eventData
     */
    beforeTransit(eventData) {
        if (eventData.data.paymentMethod !== this.options.paymentMethod || eventData.stopped) {
            return;
        }

        const cardElement = this.getPaymentElement();
        const stripe = stripeClient.getStripeInstance(this.options);
        const self = this;

        try {
            mediator.execute('showLoading');
            stripe.createPaymentMethod({
                type: 'card',
                card: cardElement
            }).then(function(result) {
                if (result.error) {
                    const error = result.error;
                    console.log(error);

                    if (error.type === 'validation_error') {
                        self.clearValidationMessages();
                        self.showValidationMessages(error.message);
                    } else {
                        this.showErrorMessage(__(self.messages.card_data_incorrect));
                    }
                } else if (result.paymentMethod.id) {
                    const additionalData = {
                        stripePaymentMethodId: result.paymentMethod.id
                    };

                    mediator.trigger('checkout:payment:additional-data:set', JSON.stringify(additionalData));
                    eventData.resume();
                } else {
                    this.showErrorMessage(__(self.messages.card_data_incorrect));
                }

                mediator.execute('hideLoading');
            }).catch(function(error) {
                console.log(error);
                self.showErrorMessage(__(self.messages.create_payment_method_failed));
                mediator.execute('hideLoading');
            });
        } catch (e) {
            console.log(e);
            self.showErrorMessage(__(self.messages.create_payment_method_failed));
            mediator.execute('hideLoading');
        }

        eventData.stopped = true;
    },

    /**
     * Get payment element which initialized and stored in stripe client component.
     *
     * @returns {jQuery|HTMLElement|*}
     */
    getPaymentElement() {
        const elements = stripeClient.getStripeElements(this.elementType, this.options);
        return elements.getElement(this.elementType);
    },

    /**
     * Hide card element if selected another payment method and show again if stripe method selected.
     */
    paymentMethodChanged(eventData) {
        if (eventData.paymentMethod === this.options.paymentMethod) {
            const cardElement = this.getPaymentElement();
            this.mountCardElement(cardElement);
            this.stripeCardViewShow();
        } else {
            this.stripeCardViewHide();
        }
    },

    /**
     * Show and clear validation errors.
     *
     * @param event
     */
    handleCardValidation(event) {
        this.clearValidationMessages();
        if (event.error) {
            this.showValidationMessages(event.error.message);
        }
    },

    /**
     *
     * @param {String} message
     */
    showValidationMessages(message) {
        this.cardValidationContainer.append(errorsTemplate({message: message}));
    },

    /**
     *
     * @param {String} message
     */
    showErrorMessage(message) {
        mediator.execute(
            'showFlashMessage',
            'error',
            message
        );
    },

    clearValidationMessages() {
        this.cardValidationContainer.empty();
    },

    /**
     * @returns {string}
     */
    resolveContainer() {
        if ($(this.options.cardContainer).length ) {
            return this.options.cardContainer;
        }

        return this.options.stripePaymentContainer;
    },

    stripeCardViewShow() {
        if (this.stripeCard) {
            this.stripeCard.view.$el.show();
            this.stripeCard.view.adjustElement();
        }
    },

    stripeCardViewHide() {
        if (this.stripeCard) {
            this.stripeCard.view.$el.hide();
        }
    },

    /**
     * Unbind global events handlers.
     */
    dispose() {
        if (this.disposed) {
            return;
        }

        this.stripeCardViewHide();
        const paymentElement = this.getPaymentElement();
        paymentElement.off('change');

        StripePaymentCardComponent.__super__.dispose.call(this);
    }
});

export default StripePaymentCardComponent;
