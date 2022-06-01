define(function(require) {
    'use strict';

    const _ = require('underscore');
    const mediator = require('oroui/js/mediator');
    const BaseComponent = require('oroui/js/app/components/base/component');
    const stripeClient = require('orostripe/js/app/components/stripe-client');
    const __ = require('orotranslation/js/translator');

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
                        const stripe = stripeClient.getStripeInstance(this.options);
                        stripe.handleCardAction(response.payment_intent_client_secret)
                            .then(function(result) {
                                if (result.error) {
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
                                    mediator.execute('hideLoading');
                                } else if (result.hasOwnProperty('paymentIntent')) {
                                    const paymentIntentId = result.paymentIntent.id;
                                    mediator.execute(
                                        'redirectTo',
                                        {url: eventData.responseData.returnUrl + '?paymentIntentId=' + paymentIntentId},
                                        {redirect: true}
                                    );
                                }
                            }).catch(function(error) {
                                console.log(error);
                                mediator.execute(
                                    'showFlashMessage',
                                    'error',
                                    __(self.messages.handle_payment_action_failed)
                                );
                            });
                    } else {
                        mediator.execute('redirectTo', {url: eventData.responseData.errorUrl}, {redirect: true});
                    }
                }
            }
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

    return StripePaymentComponent;
});
