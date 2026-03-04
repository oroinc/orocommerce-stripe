@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroStripePaymentBundle:SingleStepCheckout/stripe_payment_element__singlestep_checkout.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__charge.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml

Feature: Stripe Payment Element - Single-Step Checkout - Declined Payment

  Scenario: Feature Background
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |
    And I activate "Single Page Checkout" workflow

  Scenario: Finish single-step checkout with Stripe Payment Element
    Given I proceed as the Buyer
    And I signed in as AmandaRCole@example.org on the store frontend
    And I open page with shopping list Shopping List
    And I click "Create Order"
    And I wait "Submit Order" button
    When I click "Submit Order"
    And I fill "SingleStep Checkout Stripe Payment Element Form" with:
      | Stripe Card Number | 4000 0000 0000 9235 |
      | Stripe Exp Date    | 12 / 35             |
      | Stripe CVC         | 111                 |
      | Stripe ZIP         | 12345               |
    And I click "Pay Order"
    Then I should see "Your card was declined"
    And Page title equals to "Checkout - Checkout"
