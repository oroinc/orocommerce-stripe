import mediator from 'oroui/js/mediator';
import BaseComponent from 'oroui/js/app/components/base/component';
import validationErrorTemplate from 'tpl-loader!orostripepayment/templates/validation-error.html';
import _ from 'underscore';
import __ from 'orotranslation/js/translator';

const StripePaymentElementInvoicePaymentComponent = BaseComponent.extend({
    /**
     * @inheritDoc
     */
    relatedSiblingComponents: {
        invoicePaymentComponent: 'invoice-payment-component'
    },

    /**
     * @inheritDoc
     */
    optionNames: BaseComponent.prototype.optionNames.concat([
        'paymentMethodIdentifier',
        'stripeOptions',
        'stripeElementsObjectOptions',
        'stripePaymentElementOptions'
    ]),

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
     * @property {jQuery.Element}
     */
    $paymentContainer: null,

    /**
     * @property {jQuery.Element}
     */
    $validationContainer: null,

    /**
     * @property {Stripe}
     */
    stripeInstance: null,

    /**
     * @property {Stripe.elements}
     */
    stripeElementsInstance: null,

    /**
     * @property {Stripe.element}
     */
    stripeElement: null,

    messages: {
        handle_payment_action_failed: 'oro.stripe.error_messages.handle_payment_action_failed'
    },

    /**
     * @inheritDoc
     */
    constructor: function StripePaymentElementInvoicePaymentComponent(options) {
        options.stripePaymentElementOptions = _.extend(
            this.defaultOptions.stripePaymentElementOptions,
            options.stripePaymentElementOptions
        );

        StripePaymentElementInvoicePaymentComponent.__super__.constructor.call(this, options);
    },

    /**
     * @inheritDoc
     */
    initialize(options) {
        StripePaymentElementInvoicePaymentComponent.__super__.initialize.call(this, options);

        this.$el = options._sourceElement;
        this.invoicePaymentModel = this.invoicePaymentComponent.invoicePaymentModel;
        this.$paymentContainer = this.$el.find('[data-role="stripe-payment-element-container"]');
        this.$validationContainer = this.$el.find('[data-role="stripe-validation-container"]');

        this.onInvoicePaymentModelChangePaymentMethodIdentifier();
    },

    /**
     * @inheritDoc
     */
    delegateListeners() {
        this.listenTo(
            this.invoicePaymentModel,
            'change:paymentMethodIdentifier',
            this.onInvoicePaymentModelChangePaymentMethodIdentifier.bind(this)
        );

        this.listenTo(
            this.invoicePaymentModel,
            'change:state',
            this.onInvoicePaymentModelChangeState.bind(this)
        );

        /**
         * Set validation handler on 'change' event according to Stripe library recommendations.
         * @see https://stripe.com/docs/js/element/input_validation
         */
        this.listenTo(this.stripeElement, 'change', this.handleValidation.bind(this));
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

    onInvoicePaymentModelChangePaymentMethodIdentifier() {
        if (this.invoicePaymentModel.getPaymentMethodIdentifier() !== this.paymentMethodIdentifier) {
            this.$paymentContainer.hide();

            return;
        }

        this.$paymentContainer.show();

        try {
            this.stripeElementsInstance = this.getStripeInstance().elements(this.stripeElementsObjectOptions);
            this.stripePaymentElement = this.stripeElementsInstance
                .create(this.stripeElementType, this.stripePaymentElementOptions);

            this.stripePaymentElement.on('loaderror', event => {
                mediator.execute('showFlashMessage', 'error', event.error?.message || 'Stripe load error');
            });

            this.stripePaymentElement.mount(this.$paymentContainer[0]);

            this.invoicePaymentComponent.enableSubmit();
        } catch (e) {
            mediator.execute('showFlashMessage', 'error', e.message || 'Stripe init error');
        }
    },

    onInvoicePaymentModelChangeState() {
        if (this.invoicePaymentModel.isSubmitStarted()) {
            this.onInvoicePaymentSubmitStart();
        }

        if (this.invoicePaymentModel.isSubmitFinished()) {
            this.onInvoicePaymentSubmitFinish();
        }
    },

    onInvoicePaymentSubmitStart() {
        if (!this.invoicePaymentModel.isSubmitStarted()) {
            return;
        }

        if (this.invoicePaymentModel.getPaymentMethodIdentifier() !== this.paymentMethodIdentifier) {
            return;
        }

        this.invoicePaymentModel.pauseSubmit();

        try {
            this.stripeElementsInstance.submit()
                .then(stripeResult => {
                    if (stripeResult.selectedPaymentMethod) {
                        this.getStripeInstance()
                            .createConfirmationToken({elements: this.stripeElementsInstance})
                            .then(stripeResult => {
                                if (stripeResult.confirmationToken) {
                                    const confirmationToken = stripeResult.confirmationToken;
                                    this.invoicePaymentModel
                                        .setPaymentMethodData({
                                            confirmationToken: {
                                                id: confirmationToken.id,
                                                paymentMethodPreview: {
                                                    type: confirmationToken.payment_method_preview.type
                                                }
                                            }
                                        });
                                    this.invoicePaymentModel.resumeSubmit();
                                } else {
                                    this.handleStripeResultErrorOnSubmitStart(stripeResult);
                                }
                            })
                            .catch(error => this.handleStripeResultErrorOnSubmitStart({error: {message: error}}));
                    } else {
                        this.handleStripeResultErrorOnSubmitStart(stripeResult);
                    }
                })
                .catch(error => this.handleStripeResultErrorOnSubmitStart({error: {message: error}}));
        } catch (error) {
            this.invoicePaymentModel.cancelSubmit(__(this.messages.handle_payment_action_failed));

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

                this.invoicePaymentModel.cancelSubmit();
            } else {
                this.invoicePaymentModel.cancelSubmit(stripeResult.error.message);
            }
        } else {
            this.invoicePaymentModel.cancelSubmit(__(this.messages.handle_payment_action_failed));
        }
    },

    onInvoicePaymentSubmitFinish() {
        if (!this.invoicePaymentModel.isSubmitFinished()) {
            return;
        }

        if (this.invoicePaymentModel.getPaymentMethodIdentifier() !== this.paymentMethodIdentifier) {
            return;
        }

        const paymentMethodResult = this.invoicePaymentModel.getPaymentMethodResult();

        try {
            if (paymentMethodResult.successful) {
                this.invoicePaymentModel.succeedPayment();

                if (paymentMethodResult.successUrl) {
                    mediator.execute('redirectTo', {url: paymentMethodResult.successUrl}, {redirect: true});
                }
            } else {
                if (paymentMethodResult.requiresAction) {
                    this.handleNextAction();
                } else if (paymentMethodResult.purchasePartial && paymentMethodResult.partiallyPaidUrl) {
                    this.invoicePaymentModel.succeedPayment();

                    mediator.execute('redirectTo', {url: paymentMethodResult.partiallyPaidUrl}, {redirect: true});
                } else {
                    this.invoicePaymentModel.failPayment();

                    if (paymentMethodResult.errorUrl) {
                        mediator.execute('redirectTo', {url: paymentMethodResult.errorUrl}, {redirect: true});
                    }
                }
            }
        } catch (error) {
            this.invoicePaymentModel.failPayment();

            console.error(error);
        }
    },

    handleNextAction() {
        const paymentMethodResult = this.invoicePaymentModel.getPaymentMethodResult();

        try {
            this.getStripeInstance()
                .handleNextAction({clientSecret: paymentMethodResult.paymentIntentClientSecret})
                .then(stripeResult => {
                    if (stripeResult.paymentIntent) {
                        this.invoicePaymentModel.succeedPayment();

                        const returnUrl = paymentMethodResult.returnUrl + '?paymentIntentId=' +
                            stripeResult.paymentIntent.id;
                        mediator.execute('redirectTo', {url: returnUrl}, {redirect: true});
                    } else {
                        this.handleStripeResultErrorOnSubmitFinish(stripeResult);

                        mediator.execute('redirectTo', {url: paymentMethodResult.errorUrl}, {redirect: true});
                    }
                })
                .catch(error => this.handleStripeResultErrorOnSubmitFinish({error: {message: error}}));
        } catch (error) {
            this.invoicePaymentModel.failPayment();

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

                this.invoicePaymentModel.failPayment();
            } else {
                this.invoicePaymentModel.failPayment(stripeResult.error.message);
            }
        } else {
            this.invoicePaymentModel.failPayment();
        }
    }
});

/**
 * @exports StripePaymentElementInvoicePaymentComponent
 */
export default StripePaymentElementInvoicePaymentComponent;
