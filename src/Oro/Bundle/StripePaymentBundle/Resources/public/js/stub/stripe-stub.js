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

                    mount: function(selectorOrElement) {
                        this.container = typeof selectorOrElement === 'string'
                            ? document.querySelector(selectorOrElement)
                            : selectorOrElement;

                        this.container.appendChild(this._buildCardForm());
                        this.mounted = true;
                    },

                    unmount: function() {
                        if (null !== this.container) {
                            this.container.innerHTML = '';
                        }

                        this.mounted = false;
                    },

                    on: function(eventType, callable) {
                        // Events unbinding is not supported by this mock.
                        // Cases with validation is not tested by behat tests.
                    },

                    off: function(eventType) {
                        // Events unbinding is not supported by this mock.
                    },

                    _buildCardForm: function() {
                        const wrapper = document.createElement('div');
                        wrapper.setAttribute('class', 'test-stripe-container');

                        const styleElement = document.createElement('style');
                        styleElement.innerText = `
                            .test-stripe-container input {
                                width: 50%;
                            }
                        `;
                        wrapper.appendChild(styleElement);

                        wrapper.appendChild(this._createElement('text', 'cardnumber', 'Card number'));
                        wrapper.appendChild(this._createElement('text', 'exp-date', 'MM / YY'));
                        wrapper.appendChild(this._createElement('text', 'cvc', 'CVC'));
                        wrapper.appendChild(this._createElement('text', 'postal', 'ZIP'));

                        return wrapper;
                    },

                    _createElement: function(type, name, placeholder) {
                        const element = document.createElement('input');
                        element.type = type;
                        element.name = name;
                        element.placeholder = placeholder;

                        return element;
                    },

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
