import $ from 'jquery';
import mediator from 'oroui/js/mediator';
import BaseComponent from 'oroui/js/app/components/base/component';
import DialogWidget
    from 'orostripepayment/js/app/views/checkout-multi-step/stripe-payment-element-checkout-multi-step-dialog-widget';
import validationErrorTemplate from 'tpl-loader!orostripepayment/templates/validation-error.html';
import _ from 'underscore';
import __ from 'orotranslation/js/translator';

const StripePaymentElementCheckoutMultiStepComponent = BaseComponent.extend({
    /**
     * @inheritDoc
     */
    optionNames: BaseComponent.prototype.optionNames.concat([
        'paymentMethodIdentifier',
        'stripeOptions',
        'stripeElementsObjectOptions',
        'stripePaymentElementOptions'
    ]),

    /**
     * @inheritDoc
     */
    listen: {
        'checkout:before-submit mediator': 'onPlaceOrderSubmitBefore'
    },

    defaultOptions: {
        stripePaymentElementOptions: {}
    },

    /**
     * @property {string}
     */
    paymentMethodIdentifier: null,

    /**
     * @property {string}
     */
    stripeElementType: 'payment',

    /**
     * @property {object}
     */
    stripeOptions: {},

    /**
     * @property {object}
     */
    stripeElementsObjectOptions: {},

    /**
     * @property {object}
     */
    stripePaymentElementOptions: {},

    /**
     * @property {Function}
     */
    paymentMethodFormTemplate: null,

    /**
     * @property {jQuery.Element}
     */
    $paymentContainer: null,

    /**
     * @property {jQuery.Element}
     */
    $validationContainer: null,

    /**
     * @property {jQuery.Element}
     */
    $checkoutForm: null,

    /**
     * @property {Stripe}
     */
    stripeInstance: null,

    /**
     * @property {Stripe.elements}
     */
    stripeElementsInstance: null,

    messages: {
        handle_payment_action_failed: 'oro.stripe.error_messages.handle_payment_action_failed'
    },

    /**
     * @inheritDoc
     */
    constructor: function StripePaymentElementCheckoutMultiStepComponent(options) {
        options.stripePaymentElementOptions = _.extend(
            this.defaultOptions.stripePaymentElementOptions,
            options.stripePaymentElementOptions
        );

        StripePaymentElementCheckoutMultiStepComponent.__super__.constructor.call(this, options);
    },

    /**
     * @inheritDoc
     */
    initialize(options) {
        StripePaymentElementCheckoutMultiStepComponent.__super__.initialize.call(this, options);

        this.$el = options._sourceElement;
        this.$checkoutForm = this.getForm($('.checkout-form__submit[type="submit"]'));
        this.paymentMethodFormTemplate = _.template(
            this.$el.find('[data-role="checkout-payment-method-form-template"]').html()
        );
    },

    /**
     * @inheritDoc
     */
    delegateListeners() {
        this.listenTo(
            mediator,
            'checkout:before-submit',
            this.onPlaceOrderSubmitBefore.bind(this)
        );
    },

    /**
     * @param {jQuery.Element} $element
     *
     * @returns {jQuery.Element}
     */
    getForm($element) {
        return $element.prop('form') ? $($element.prop('form')) : $element.closest('form');
    },

    /**
     * @returns {Stripe}
     */
    getStripeInstance() {
        if (!this.stripeInstance) {
            // eslint-disable-next-line no-undef,new-cap
            this.stripeInstance = Stripe(this.stripeOptions.apiPublicKey, _.omit(this.stripeOptions, ['apiPublicKey']));
        }

        return this.stripeInstance;
    },

    /**
     * Show and clear validation errors.
     *
     * @param {object} event
     */
    handleValidation(event) {
        this.clearValidationContainer();
        if (event.error) {
            this.showValidationMessage(event.error.message);
        }
    },

    clearValidationContainer() {
        this.$validationContainer.empty();
    },

    /**
     * @param {string} message
     */
    showValidationMessage(message) {
        this.$validationContainer.append(validationErrorTemplate({message: message}));
    },

    /**
     * @param {Object} eventData
     *
     * See "checkout:before-submit" event.
     */
    onPlaceOrderSubmitBefore(eventData) {
        const stripePaymentDialogData = eventData.extraData.stripePaymentDialog ?? {};

        if (stripePaymentDialogData.preventOpen) {
            return;
        }

        eventData.stopped = true;

        const $widgetContent = $('<div class="widget-content"/>').append(this.paymentMethodFormTemplate());
        this.$paymentContainer = $widgetContent.find('[data-role="stripe-payment-element-container"]');
        this.$validationContainer = $widgetContent.find('[data-role="stripe-validation-container"]');

        this.dialogWidget = new DialogWidget();
        this.listenTo(this.dialogWidget, 'adoptedFormSubmitClick', this.onPaymentDialogSubmit.bind(this));
        this.dialogWidget.setContent($widgetContent);

        try {
            this.stripeElementsInstance = this.getStripeInstance().elements(this.stripeElementsObjectOptions);
            this.stripePaymentElement = this.stripeElementsInstance
                .create(this.stripeElementType, this.stripePaymentElementOptions);

            this.listenTo(this.stripePaymentElement, 'change', this.handleValidation.bind(this));
            this.listenTo(this.stripePaymentElement, 'loaderror', event => {
                mediator.execute('showFlashMessage', 'error', event.error?.message || 'Stripe load error');
            });

            this.stripePaymentElement.mount(this.$paymentContainer[0]);
        } catch (e) {
            mediator.execute('showFlashMessage', 'error', e.message || 'Stripe init error');
        }
    },

    onPaymentDialogSubmit() {
        try {
            this.stripeElementsInstance.submit()
                .then(stripeResult => {
                    if (!stripeResult.selectedPaymentMethod) {
                        this.handleStripeResultErrorOnSubmitStart(stripeResult);

                        return;
                    }

                    this.getStripeInstance()
                        .createConfirmationToken({elements: this.stripeElementsInstance})
                        .then(stripeResult => {
                            if (stripeResult.confirmationToken) {
                                const confirmationToken = stripeResult.confirmationToken;

                                const additionalData = {
                                    confirmationToken: {
                                        id: confirmationToken.id,
                                        paymentMethodPreview: {
                                            type: confirmationToken.payment_method_preview.type
                                        }
                                    }
                                };

                                mediator.trigger(
                                    'checkout:payment:additional-data:set',
                                    JSON.stringify(additionalData)
                                );

                                mediator.once(
                                    'checkout:place-order:response',
                                    this.onPlaceOrderSubmitAfter.bind(this)
                                );

                                this.$checkoutForm.trigger('submit', {stripePaymentDialog: {preventOpen: true}});
                            } else {
                                this.handleStripeResultErrorOnSubmitStart(stripeResult);
                            }
                        })
                        .catch(error => this.handleStripeResultErrorOnSubmitStart({error: {message: error}}));
                })
                .catch(error => this.handleStripeResultErrorOnSubmitStart({error: {message: error}}));
        } catch (error) {
            mediator.execute('showFlashMessage', 'error', __(this.messages.handle_payment_action_failed));

            console.error(error);
        }
    },

    /**
     * @param {object} stripeResult
     */
    handleStripeResultErrorOnSubmitStart(stripeResult) {
        if (stripeResult.error) {
            if (stripeResult.error.type === 'validation_error') {
                this.clearValidationContainer();
                this.showValidationMessage(stripeResult.error.message);
            } else {
                mediator.execute('showFlashMessage', 'error', stripeResult.error.message);
            }
        } else {
            mediator.execute('showFlashMessage', 'error', __(this.messages.handle_payment_action_failed));
        }
    },

    /**
     * @param {object} eventData
     *
     * See "checkout:place-order:response" event.
     */
    onPlaceOrderSubmitAfter(eventData) {
        const {responseData} = eventData;

        try {
            if (responseData.successful) {
                if (responseData.successUrl) {
                    mediator.execute('redirectTo', {url: responseData.successUrl}, {redirect: true});
                }
            } else {
                if (responseData.requiresAction) {
                    this.handleNextAction(responseData);
                } else if (responseData.purchasePartial && responseData.partiallyPaidUrl) {
                    mediator.execute('redirectTo', {url: responseData.partiallyPaidUrl}, {redirect: true});
                } else {
                    if (responseData.error) {
                        this.clearValidationContainer();
                        this.showValidationMessage(responseData.error);
                    } else if (responseData.errorUrl) {
                        mediator.execute('redirectTo', {url: responseData.errorUrl}, {redirect: true});
                    }
                }
            }
        } catch (error) {
            console.error(error);
        }

        eventData.stopped = true;
        mediator.execute('hideLoading');
    },

    /**
     * @param {object} responseData
     */
    handleNextAction(responseData) {
        try {
            this.getStripeInstance()
                .handleNextAction({clientSecret: responseData.paymentIntentClientSecret})
                .then(stripeResult => {
                    if (stripeResult.paymentIntent) {
                        const returnUrl = responseData.returnUrl + '?paymentIntentId=' +
                            stripeResult.paymentIntent.id;
                        mediator.execute('redirectTo', {url: returnUrl}, {redirect: true});
                    } else {
                        this.handleStripeResultErrorOnSubmitFinish(stripeResult);

                        mediator.execute('redirectTo', {url: responseData.errorUrl}, {redirect: true});
                    }
                })
                .catch(error => this.handleStripeResultErrorOnSubmitFinish({error: {message: error}}));
        } catch (error) {
            console.error(error);
        }
    },

    /**
     * @param {object} stripeResult
     */
    handleStripeResultErrorOnSubmitFinish(stripeResult) {
        if (stripeResult.error) {
            if (stripeResult.error.type === 'validation_error') {
                this.clearValidationContainer();
                this.showValidationMessage(stripeResult.error.message);
            } else {
                mediator.execute('showFlashMessage', 'error', stripeResult.error.message);
            }
        };
    }
});

export default StripePaymentElementCheckoutMultiStepComponent;
