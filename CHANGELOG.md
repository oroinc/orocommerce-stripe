The upgrade instructions are available at [Oro documentation website](https://doc.oroinc.com/master/backend/setup/upgrade-to-new-version/).

The current file describes significant changes in the code that may affect the upgrade of your customizations.

## UNRELEASED

### Added

#### StripeBundle
- added optional `$paymentMethodIdentifier` constructor argument to `StripeEvent` to make it aware of the payment methods 
not equal to one in `$paymentMethodConfig`

#### StripePaymentBundle
* added new implementation of Stripe API
* added new `\Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\StripePaymentElementMethod`

### Changed

#### StripeBundle
- changed the Stripe Payment Method label to "Stripe (Legacy)"
- updated `StripeFilter` to add ability to specify more allowed routes to enable `stripe.js` on other pages
- fixed the `oro_stripe_order_payment_transaction_cancel` action that broke the cancel action for non-Stripe payment methods
- fixed the `oro_stripe_order_payment_transaction_refund` action that broke the refund action for non-Stripe payment methods
