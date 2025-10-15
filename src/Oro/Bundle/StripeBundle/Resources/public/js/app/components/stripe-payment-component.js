import _ from 'underscore';
import mediator from 'oroui/js/mediator';
import BaseComponent from 'oroui/js/app/components/base/component';
import stripeClient from 'orostripe/js/app/components/stripe-client';
import __ from 'orotranslation/js/translator';

const StripePaymentComponent = BaseComponent.extend({
    listen: {
        'checkout:place-order:response mediator': 'placeOrderResponse'
    },
    messages: {
        handle_payment_action_failed: 'oro.stripe.error_messages.handle_payment_action_failed'
    },

    /**
     * @inheritdoc
     */
    constructor: function StripePaymentComponent(options) {
        StripePaymentComponent.__super__.constructor.call(this, options);
    },

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this.options = _.extend({}, options);
        StripePaymentComponent.__super__.initialize.call(this, options);
    },

    /**
     * Handle payment response. In case if additional user actions required handle additional card actions with
     * Stripe library tool and handle response redirecting to success or error page.
     *
     * @param {Object} eventData
     */
    placeOrderResponse: function(eventData) {
        const response = eventData.responseData;
        if (response.paymentMethod === this.options.paymentMethod) {
            if (response.successful === true) {
                eventData.stopped = true;
                mediator.execute('redirectTo', {url: eventData.responseData.successUrl}, {redirect: true});
            } else {
                eventData.stopped = true;
                if (response.requires_action) {
                    // Handle additional user actions (3D secure validation, etc.. )
                    const self = this;

                    const intentErrorHandler = function(result) {
                        mediator.execute(
                            'showFlashMessage',
                            'error',
                            result.error.message
                        );
                        mediator.execute(
                            'redirectTo',
                            {url: eventData.responseData.errorUrl},
                            {redirect: true}
                        );
                    };

                    const exceptionHandler = function(error) {
                        console.log(error);
                        mediator.execute(
                            'showFlashMessage',
                            'error',
                            __(self.messages.handle_payment_action_failed)
                        );
                    };

                    const stripe = stripeClient.getStripeInstance(this.options);
                    if (_.has(response, 'payment_intent_client_secret')) {
                        stripe.handleCardAction(response.payment_intent_client_secret)
                            .then(function(result) {
                                if (result.error) {
                                    intentErrorHandler(result);
                                } else if (result.hasOwnProperty('paymentIntent')) {
                                    const intentId = result.paymentIntent.id;
                                    mediator.execute(
                                        'redirectTo',
                                        {url: eventData.responseData.returnUrl + '?paymentIntentId=' + intentId},
                                        {redirect: true}
                                    );
                                }
                            })
                            .catch(exceptionHandler)
                            .finally(function() {
                                mediator.execute('hideLoading');
                            });
                    } else if (_.has(response, 'setup_intent_client_secret')) {
                        stripe.confirmCardSetup(response.setup_intent_client_secret)
                            .then(function(result) {
                                if (result.error) {
                                    intentErrorHandler(result);
                                } else if (result.hasOwnProperty('setupIntent')) {
                                    const setupIntentId = result.setupIntent.id;
                                    mediator.execute(
                                        'redirectTo',
                                        {url: eventData.responseData.returnUrl + '?setupIntentId=' + setupIntentId},
                                        {redirect: true}
                                    );
                                }
                            })
                            .catch(exceptionHandler)
                            .finally(function() {
                                mediator.execute('hideLoading');
                            });
                    }
                } else if (this.isPaidPartially(response)) {
                    mediator.execute(
                        'redirectTo',
                        {url: response.partiallyPaidUrl},
                        {redirect: true}
                    );
                } else {
                    mediator.execute('redirectTo', {url: eventData.responseData.errorUrl}, {redirect: true});
                }
            }
        }
    },

    /**
     * Check if payment is partially successful and has specific url to redirect.
     *
     * @param {Object} response
     */
    isPaidPartially: function(response) {
        return _.has(response, 'purchasePartial') && response.purchasePartial === true &&
            _.has(response, 'partiallyPaidUrl');
    },

    /**
     * Unbind global event handlers
     */
    dispose: function() {
        if (this.disposed) {
            return;
        }

        StripePaymentComponent.__super__.dispose.call(this);
    }
});

export default StripePaymentComponent;
