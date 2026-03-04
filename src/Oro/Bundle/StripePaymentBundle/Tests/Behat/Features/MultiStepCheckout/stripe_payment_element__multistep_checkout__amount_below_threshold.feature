@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:ShippingRuleFreeShippingUnconditionally.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__charge.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml
@fixture-OroStripePaymentBundle:MultiStepCheckout/stripe_payment_element__multistep_checkout__amount_below_threshold.yml

Feature: Stripe Payment Element - Multi-Step Checkout - Amount Below Threshold

  Scenario: Check that Stripe Payment Element is not available for amounts below the supported threshold (0.50$)
    Given I signed in as AmandaRCole@example.org on the store frontend
    When I open page with shopping list Shopping List
    And I click "Create Order"
    Then Page title equals to "Billing Information - Checkout"
    When I click "Continue"
    Then Page title equals to "Shipping Information - Checkout"
    When I click "Continue"
    Then Page title equals to "Shipping Method - Checkout"
    When I check "Flat Rate" on the checkout page
    And I click "Continue"
    Then Page title equals to "Payment - Checkout"
    And I should see "No payment methods are available, please contact us to complete the order submission."
