define(function(require) {
    'use strict';

    const $ = require('jquery');
    const mediator = require('oroui/js/mediator');
    const BaseComponent = require('oroui/js/app/components/base/component');
    const stripeClient = require('orostripe/js/app/components/stripe-client');
    const errorsTemplate = require('tpl-loader!orostripe/templates/field-errors.html');
    const __ = require('orotranslation/js/translator');

    const StripePaymentCardComponent = BaseComponent.extend({
        relatedSiblingComponents: {
            // to make sure that a stripeCard is initialized first (e.g. on single page checkout)
            stripeCard: 'stripe-card-view-component'
        },

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
        initialize: function(options) {
            StripePaymentCardComponent.__super__.initialize.call(this, options);
            this.options = Object.assign({}, this.defaults, options);
            this.$el = $(options._sourceElement);

            // Try to initialize card element.
            this.initCardElement();
        },

        /**
         * Initialize basic card elements using Stripe standard library tools, to be able to send securely payment card
         * data to the Stripe service.
         */
        initCardElement: function() {
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
            this.cardValidationContainer = $(this.options.validationErrorContainerId);

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
        paymentMethodIsSelected: function() {
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
        mountCardElement: function(cardElement) {
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
        beforeTransit: function(eventData) {
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
        getPaymentElement: function() {
            const elements = stripeClient.getStripeElements(this.elementType, this.options);
            return elements.getElement(this.elementType);
        },

        /**
         * Hide card element if selected another payment method and show again if stripe method selected.
         */
        paymentMethodChanged: function(eventData) {
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
        handleCardValidation: function(event) {
            this.clearValidationMessages();
            if (event.error) {
                this.showValidationMessages(event.error.message);
            }
        },

        /**
         *
         * @param {String} message
         */
        showValidationMessages: function(message) {
            this.cardValidationContainer.append(errorsTemplate({message: message}));
        },

        /**
         *
         * @param {String} message
         */
        showErrorMessage: function(message) {
            mediator.execute(
                'showFlashMessage',
                'error',
                message
            );
        },

        clearValidationMessages: function() {
            this.cardValidationContainer.empty();
        },

        /**
         * Use alternative container (cardContainer - used when SinglePageCheckout workflow is used) otherwise use
         * basic container (stripePaymentContainer).
         *
         * @returns {string}
         */
        resolveContainer: function() {
            if ($(this.options.cardContainer).length) {
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
        dispose: function() {
            if (this.disposed) {
                return;
            }

            this.stripeCardViewHide();
            const paymentElement = this.getPaymentElement();
            paymentElement.off('change');

            StripePaymentCardComponent.__super__.dispose.call(this);
        }
    });

    return StripePaymentCardComponent;
});
