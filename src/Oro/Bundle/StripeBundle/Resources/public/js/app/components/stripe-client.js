define(function(require) {
    'use strict';

    /**
     * We should use the same Stripe instance for different types of manipulation (card element initialization),
     * createPaymentMethod (send card data to Stripe Service), etc..
     * This component is used to store initialized instances of Stripe object and Stripe Payment Element object.
     */
    return {
        instances: [],
        elements: [],

        /**
         *
         * @param options
         * @returns {*}
         */
        getStripeInstance: function(options) {
            const identifier = options.paymentMethod;
            if (!this.instances.hasOwnProperty(identifier)) {
                this.initStripeInstance(options);
            }

            return this.instances[identifier];
        },

        /**
         * Try to initialize Stripe instance if Stripe.js library loaded.
         *
         * @param options
         */
        initStripeInstance: function(options) {
            const identifier = options.paymentMethod;
            if (typeof Stripe !== undefined) {
                const stripeInstance = this.createStripeInstance(options);
                this.instances[identifier] = stripeInstance;
            } else {
                throw Error('Unable to create Stripe instance. Probably Stripe.js library has not been loaded');
            }
        },

        /**
         * According to https://stripe.com/docs/js/initializing
         * Create Stripe object using options.
         *
         * @param options
         * @returns {*}
         */
        createStripeInstance: function(options) {
            // eslint-disable-next-line no-undef,new-cap
            return Stripe(options.publicKey, {
                locale: options.locale,
                apiVersion: options.apiVersion
            });
        },

        /**
         * Use elements instance to manipulate with payment elements. The same instance should be used for manipulation
         * with its payment elements. Call of Stipe.elements() creates new elements instance each time.
         *
         * @param {String} type
         * @param {Object} options
         */
        getStripeElements: function(type, options) {
            const identifier = options.paymentMethod;
            if (!this.elements[identifier]) {
                this.elements[identifier] = this.getStripeInstance(options).elements();
            }

            return this.elements[identifier];
        }
    };
});
