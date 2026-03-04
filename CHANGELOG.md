The upgrade instructions are available at [Oro documentation website](https://doc.oroinc.com/master/backend/setup/upgrade-to-new-version/).

The current file describes significant changes in the code that may affect the upgrade of your customizations.

## 7.0

### Added

#### StripePaymentBundle
* added support of Stripe Payment Element on multi-step and single-step checkout pages
* added support of Stripe Payment Element for sub-orders on checkout 
* added support of Stripe Payment Element for checkout in Storefront API
* added migration to remove StripeTransportSettings entity and its relations (labels)
* added migration to rename system configuration "oro_stripe.apple_pay_domain_verification" to "oro_stripe_payment.apple_pay_domain_verification"
* added the empty StripePaymentMethodsDataProvider (oro_stripe_payment_method) to comply with the old themes BC
* added operations oro_stripe_order_payment_transaction_cancel and oro_stripe_order_payment_transaction_refund for order payments datagrid

### Updated

#### StripePaymentBundle
* renamed StripePaymentElementMethod block name to _oro_stripe_payment_element_widget (added underscore at the beginning)

### Removed

#### StripeBundle
* removed StripeBundle and all related code, use StripePaymentBundle instead
* removed mentions of StripeBundle from documentation
* moved ApplePayVerificationController to ApplePayDomainVerificationController in StripePaymentBundle, use the new route "oro_stripe_payment_frontend_apple_pay_domain_verification" instead
* moved system configuration setting "oro_stripe.apple_pay_domain_verification" to "oro_stripe_payment.apple_pay_domain_verification"
* moved PaymentTransactionOperationAnnounceEventListener to StripePaymentBundle
* moved base implementations of operations oro_stripe_payment_transaction_cancel and oro_stripe_payment_transaction_refund to StripePaymentBundle
* moved stripe-stub.js to StripePaymentBundle

#### StripePaymentBundle
* removed unneeded oro_stripe_payment.stripe_script.provider.stripe_card_element (StripeCardElementStripeScriptProvider)

## 6.1

### Added

#### StripeBundle
* added optional `$paymentMethodIdentifier` constructor argument to `StripeEvent` to make it aware of the payment methods 
not equal to one in `$paymentMethodConfig`

#### StripePaymentBundle
* added new implementation of Stripe API
* added new `\Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\StripePaymentElementMethod`

### Changed

#### StripeBundle
* changed the Stripe Payment Method label to "Stripe (Legacy)"
* updated `StripeFilter` to add ability to specify more allowed routes to enable `stripe.js` on other pages
* fixed the `oro_stripe_order_payment_transaction_cancel` action that broke the cancel action for non-Stripe payment methods
* fixed the `oro_stripe_order_payment_transaction_refund` action that broke the refund action for non-Stripe payment methods
