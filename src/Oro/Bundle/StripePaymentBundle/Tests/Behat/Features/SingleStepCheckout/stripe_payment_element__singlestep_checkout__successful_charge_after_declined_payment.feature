@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroStripePaymentBundle:SingleStepCheckout/stripe_payment_element__singlestep_checkout.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__charge.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml

Feature: Stripe Payment Element - Single-Step Checkout - Successful Charge after Declined Payment

  Scenario: Feature Background
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |
    And I activate "Single Page Checkout" workflow

  Scenario: Finish single-step checkout with Stripe Payment Element
    Given I proceed as the Buyer
    And I signed in as AmandaRCole@example.org on the store frontend
    When I open page with shopping list Shopping List
    And I click "Create Order"
    And I wait "Submit Order" button
    And I click "Submit Order"
    And I fill "SingleStep Checkout Stripe Payment Element Form" with:
      | Stripe Card Number | 4000 0000 0000 9235 |
      | Stripe Exp Date    | 12 / 35             |
      | Stripe CVC         | 111                 |
      | Stripe ZIP         | 12345               |
    And I click "Pay Order"
    Then I should see "Your card was declined"
    And Page title equals to "Checkout - Checkout"

    When I fill "SingleStep Checkout Stripe Payment Element Form" with:
      | Stripe Card Number | 4242 4242 4242 4242 |
    And I click "Pay Order"
    Then I see the "Thank You" page with "Thank You For Your Purchase!" title
    And I should see "Your order number is"

  Scenario: Check Payment Method and Payment Status on order storefront page
    Given I proceed as the Buyer
    When I click "click here to review"
    Then I should see "Payment Method Stripe Payment Element"
    And I should see "Payment Status Paid in full"

  Scenario: Check Payment Method and Payment Status column on orders storefront page
    When I click "Account Dropdown"
    And I click "Order History"
    And I show column "Payment Method" in "PastOrdersGrid" frontend grid
    And I show column "Total" in "PastOrdersGrid" frontend grid
    And I show column "Shipping Method" in "PastOrdersGrid" frontend grid
    Then I should see following "PastOrdersGrid" grid:
      | Total  | Shipping Method | Payment Method         | Payment Status |
      | $23.00 | Flat Rate       | Stripe Payment Element | Paid in full   |

  Scenario: Check Payment Method and Payment Status column on orders back-office page
    Given I proceed as the Admin
    And I login as administrator
    When I go to Sales/ Orders
    And I show column Payment Method in grid
    And I show column Total in grid
    And I show column Currency in grid
    Then I should see following grid:
      | Total  | Currency | Payment Method         | Payment Status |
      | $23.00 | USD      | Stripe Payment Element | Paid in full   |

  Scenario: Check Payment Method and Payment Status on order back-office page
    When I click view Stripe Payment Element in grid
    Then I should see order with:
      | Payment Method | Stripe Payment Element |
      | Payment Status | Paid in full           |

  Scenario: Check Payments section on order back-office page
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type   | Amount | Successful |
      | Stripe Payment Element | Charge | $23.00 | Yes        |
    And I should see following actions for Stripe Payment Element in "Order Payment Transaction Grid":
      | Refund |
