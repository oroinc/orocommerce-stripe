The upgrade instructions are available at [Oro documentation website](https://doc.oroinc.com/master/backend/setup/upgrade-to-new-version/).

The current file describes significant changes in the code that may affect the upgrade of your customizations.

## UNRELEASED

### Added

#### StripeBundle
- added optional $paymentMethodIdentifier constructor argument to StripeEvent to make it aware of the payment methods 
not equal to one in $paymentMethodConfig 

### Changed

#### StripeBundle
- updated StripeFilter to add ability to specify more allowed routes to enable stripe.js on other pages
