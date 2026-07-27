@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroStripePaymentBundle:MultiStepCheckout/stripe_payment_element__multistep_checkout.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__charge.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml

Feature: Stripe Payment Element - Multi-Step Checkout - Declined Payment

  Scenario: Go through checkout steps with Stripe Payment Element
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
    And I should see "Stripe payment dialog will be opened after the final step of the checkout." in the "Stripe Payment Element Method On MultiStep Checkout" element
    When I check "Stripe Payment Element" on the checkout page
    And I click "Continue"
    Then Page title equals to "Order Review - Checkout"

  Scenario: Submit order with declined Stripe payment
    When I click "Expand Checkout Footer"
    Then I should see Checkout Totals with data:
      | Subtotal | $20.00 |
      | Shipping | $3.00  |
    And should see "Total: $23.00"
    And Checkout "Order Summary Products Grid" should contain products:
      | 400-Watt Bulb Work Light | 2 | items |
    When I click "Submit Order"
    And I fill "MultiStep Checkout Stripe Payment Element Form" with:
      | Stripe Card Number | 4000 0000 0000 9235 |
      | Stripe Exp Date    | 12 / 35             |
      | Stripe CVC         | 111                 |
      | Stripe ZIP         | 12345               |
    And I click "Pay Order"
    Then I should see "Your card was declined"
    And Page title equals to "Order Review - Checkout"
