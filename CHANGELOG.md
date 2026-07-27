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
* added support of Stripe Payment Element on multi-step and single-step checkout pages
* added support of Stripe Payment Element for sub-orders on checkout
* added support of Stripe Payment Element for checkout in Storefront API
* added operations oro_stripe_payment_order_payment_transaction_cancel, oro_stripe_payment_order_payment_transaction_refund and oro_stripe_payment_order_payment_transaction_re_authorize for order payments datagrid

### Changed

#### StripeBundle
- changed the Stripe Payment Method label to "Stripe (Legacy)"
- updated `StripeFilter` to add ability to specify more allowed routes to enable `stripe.js` on other pages
- fixed the `oro_stripe_order_payment_transaction_cancel` action that broke the cancel action for non-Stripe payment methods
- fixed the `oro_stripe_order_payment_transaction_refund` action that broke the refund action for non-Stripe payment methods

#### StripePaymentBundle
* renamed StripePaymentElementMethod block name to _oro_stripe_payment_element_widget (added underscore at the beginning)
