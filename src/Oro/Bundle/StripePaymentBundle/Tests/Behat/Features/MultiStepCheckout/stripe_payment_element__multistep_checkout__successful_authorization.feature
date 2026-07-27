@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroStripePaymentBundle:MultiStepCheckout/stripe_payment_element__multistep_checkout.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__authorize.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml

Feature: Stripe Payment Element - Multi-Step Checkout - Successful Authorization

  Scenario: Feature Background
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |

  Scenario: Go through checkout steps with Stripe Payment Element
    Given I proceed as the Buyer
    And I signed in as AmandaRCole@example.org on the store frontend
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

  Scenario: Submit order with successful Stripe authorization
    When I click "Expand Checkout Footer"
    Then I should see Checkout Totals with data:
      | Subtotal | $20.00 |
      | Shipping | $3.00  |
    And should see "Total: $23.00"
    And Checkout "Order Summary Products Grid" should contain products:
      | 400-Watt Bulb Work Light | 2 | items |
    When I click "Submit Order"
    And I fill "MultiStep Checkout Stripe Payment Element Form" with:
      | Stripe Card Number | 4000 0566 5566 5556 |
      | Stripe Exp Date    | 12 / 35             |
      | Stripe CVC         | 111                 |
      | Stripe ZIP         | 12345               |
    And I click "Pay Order"
    Then I see the "Thank You" page with "Thank You For Your Purchase!" title
    And I should see "Your order number is"

  Scenario: Check Payment Method and Payment Status on order storefront page
    Given I proceed as the Buyer
    When I click "click here to review"
    Then I should see "Payment Method Stripe Payment Element"
    And I should see "Payment Status Payment authorized"

  Scenario: Check Payment Method and Payment Status column on orders storefront page
    When I click "Account Dropdown"
    And I click "Order History"
    And I show column "Payment Method" in "PastOrdersGrid" frontend grid
    And I show column "Total" in "PastOrdersGrid" frontend grid
    And I show column "Shipping Method" in "PastOrdersGrid" frontend grid
    Then I should see following "PastOrdersGrid" grid:
      | Total  | Shipping Method | Payment Method         | Payment Status     |
      | $23.00 | Flat Rate       | Stripe Payment Element | Payment authorized |

  Scenario: Check Payment Method and Payment Status column on orders back-office page
    Given I proceed as the Admin
    And I login as administrator
    When I go to Sales/ Orders
    And I show column Payment Method in grid
    And I show column Total in grid
    And I show column Currency in grid
    Then I should see following grid:
      | Total  | Currency | Payment Method         | Payment Status     |
      | $23.00 | USD      | Stripe Payment Element | Payment authorized |

  Scenario: Check Payment Method and Payment Status on order back-office page
    When I click view Stripe Payment Element in grid
    Then I should see order with:
      | Payment Method | Stripe Payment Element |
      | Payment Status | Payment authorized     |

  Scenario: Check Payments section on order back-office page
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type      | Amount | Successful |
      | Stripe Payment Element | Authorize | $23.00 | Yes        |
    And I should see following actions for Stripe Payment Element in "Order Payment Transaction Grid":
      | Capture              |
      | Cancel Authorization |

  Scenario: Capture the authorized payment
    When I click "Capture" on row "Stripe Payment Element" in grid "Order Payment Transaction Grid"
    Then I should see "Charge The Customer" in the "UiWindow Title" element
    And should see "The customer will be charged $23.00. Are you sure you want to continue?" in confirmation dialogue
    When I click "Yes, Charge"
    Then I should see "The payment of $23.00 has been captured successfully." flash message

  Scenario: Check capture transaction in Payments section
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type      | Amount | Successful |
      | Stripe Payment Element | Capture   | $23.00 | Yes        |
      | Stripe Payment Element | Authorize | $23.00 | Yes        |
    And I should see following actions for Stripe Payment Element in "Order Payment Transaction Grid":
      | Refund |

  Scenario: Check Payment Status is Paid in full after capture on order back-office page
    Then I should see order with:
      | Payment Method | Stripe Payment Element |
      | Payment Status | Paid in full           |

  Scenario: Check Payment Status is Paid in full after capture on orders back-office page
    When I go to Sales/ Orders
    And I show column Payment Method in grid
    And I show column Total in grid
    And I show column Currency in grid
    Then I should see following grid:
      | Total  | Currency | Payment Method         | Payment Status |
      | $23.00 | USD      | Stripe Payment Element | Paid in full   |

  Scenario: Check Payment Status is Paid in full on orders storefront page
    Given I proceed as the Buyer
    When I click "Account Dropdown"
    And I click "Order History"
    And I show column "Payment Method" in "PastOrdersGrid" frontend grid
    And I show column "Total" in "PastOrdersGrid" frontend grid
    And I show column "Shipping Method" in "PastOrdersGrid" frontend grid
    Then I should see following "PastOrdersGrid" grid:
      | Total  | Shipping Method | Payment Method         | Payment Status |
      | $23.00 | Flat Rate       | Stripe Payment Element | Paid in full   |

  Scenario: Check Payment Status is Paid in full on order storefront page
    When I click view "$23.00" in grid
    Then I should see "Payment Method Stripe Payment Element"
    And I should see "Payment Status Paid in full"
