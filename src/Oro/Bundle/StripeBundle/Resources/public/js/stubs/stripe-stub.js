(function() {
    /**
     * Simulates Stripe.js library behaviour. Used for behat tests.
     *
     * @param key
     * @param params
     * @returns {Object}
     * @constructor
     */
    window.Stripe = function(key, params) {
        return {
            secretKey: key,
            params: params,
            element: {
                registeredElements: [],
                paymentElement: {
                    mounted: false,
                    container: null,

                    /**
                     * Check whether payment element mounted.
                     *
                     * @returns {boolean}
                     * @private
                     */
                    _isMounted: function() {
                        return !!this.mounted;
                    },

                    /**
                     * Mount card element into DOM structure.
                     *
                     * @param {string|object} selectorOrElement
                     */
                    mount: function(selectorOrElement) {
                        this.container = typeof selectorOrElement === 'string'
                            ? document.querySelector(selectorOrElement)
                            : selectorOrElement;

                        const form = this._buildCardForm();
                        this.container.appendChild(form);
                        this.mounted = true;
                    },

                    /**
                     * Clear payment element container and mark element as unmounted.
                     */
                    unmount: function() {
                        if (null !== this.container) {
                            this.container.innerHTML = '';
                        }
                        this.mounted = false;
                    },

                    on: function(eventType, callable) {
                        // Not implemented. Cases with validation is not tested by behat tests.
                    },

                    off: function(eventType) {
                        // Unbind events is not supported by this mock.
                    },

                    /**
                     * Creates basic form elements to display card form on the page.
                     *
                     * @returns {HTMLDivElement}
                     * @private
                     */
                    _buildCardForm: function() {
                        const wrapper = document.createElement('div');
                        wrapper.setAttribute('class', 'test-stripe-container');

                        const styleElement = document.createElement('style');
                        const styles = `
                            .test-stripe-container input {
                                width: 50%;
                            }
                        `;
                        styleElement.innerText = styles;
                        wrapper.appendChild(styleElement);

                        wrapper.appendChild(this._createElement('text', 'cardnumber', 'Card number'));
                        wrapper.appendChild(this._createElement('text', 'exp-date', 'MM / YY'));
                        wrapper.appendChild(this._createElement('text', 'cvc', 'CVC'));
                        wrapper.appendChild(this._createElement('text', 'postal', 'ZIP'));

                        return wrapper;
                    },

                    /**
                     * Create input element.
                     *
                     * @param type
                     * @param name
                     * @param placeholder
                     * @returns {HTMLInputElement}
                     * @private
                     */
                    _createElement: function(type, name, placeholder) {
                        const element = document.createElement('input');
                        element.type = type;
                        element.name = name;
                        element.placeholder = placeholder;

                        return element;
                    },

                    /**
                     * Returns entered card number data.
                     *
                     * @returns {null|*}
                     */
                    getCardValue: function() {
                        if (null === this.container) {
                            return null;
                        }

                        const cardInput = this.container.querySelector('input[name="cardnumber"]');
                        return cardInput ? cardInput.value : null;
                    }
                },

                /**
                 * Create payment elements instance.
                 *
                 * @param type
                 * @param options
                 */
                create: function(type, options) {
                    const element = this.paymentElement;
                    this.registeredElements.push({
                        type: type,
                        element: element
                    });

                    return element;
                },

                /**
                 * Get previously created payment element by specific type (In our case 'card' type used only).
                 *
                 * @param type
                 * @returns {*|null}
                 */
                getElement: function(type) {
                    const item = this.registeredElements.find(item => item.type === type);
                    return item ? item.element : null;
                },

                submit: function() {
                    return new Promise(function(resolve, reject) {
                        resolve({
                            selectedPaymentMethod: 'card'
                        });
                    });
                }
            },

            /**
             * Initialize elements object instance.
             *
             * @returns {Object}
             */
            elements: function() {
                return this.element;
            },

            /**
             * Simulate createPaymentMethod behaviour of Stripe object.
             *
             * @param {Object} params
             */
            createPaymentMethod: function(params) {
                let cardValue = '';
                if (params.hasOwnProperty('card')) {
                    const element = params.card;
                    cardValue = element.getCardValue();
                    element.unmount();
                }

                return new Promise(function(resolve, reject) {
                    if (params.type === 'card') {
                        resolve({
                            paymentMethod: {
                                id: cardValue
                            }
                        });
                    } else {
                        reject(Error('Unknown payment element type'));
                    }
                });
            },

            paymentRequest: function(params) {
                return {
                    paymentMethodCallback: null,

                    canMakePayment: function() {
                        return new Promise(function(resolve, reject) {
                            resolve({
                                applePay: false,
                                googlePay: true
                            });
                        });
                    },

                    on: function(eventName, callback) {
                        this.paymentMethodCallback = callback;
                    },

                    show: function(eventName, callback) {
                        this.paymentMethodCallback({
                            paymentMethod: {
                                id: '4242 4242 4242 4242'
                            },

                            complete: function(status) {
                            }
                        });
                    }
                };
            },

            createConfirmationToken: function(params) {
                const cardValue = this.elements().getElement('payment').getCardValue();

                return new Promise(function(resolve, reject) {
                    resolve({
                        confirmationToken: {
                            id: 'tok_' + cardValue,
                            payment_method_preview: {
                                type: 'card'
                            }
                        }
                    });
                });
            }
        };
    };
})();
