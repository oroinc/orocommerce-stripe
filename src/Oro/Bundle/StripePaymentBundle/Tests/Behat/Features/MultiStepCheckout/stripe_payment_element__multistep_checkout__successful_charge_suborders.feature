@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__charge.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml
@fixture-OroStripePaymentBundle:MultiStepCheckout/stripe_payment_element__multistep_checkout_suborders.yml

Feature: Stripe Payment Element - Multi-Step Checkout - Successful Charge Sub-Orders

  Scenario: Feature Background
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |
    And I change configuration options:
      | oro_checkout.enable_line_item_grouping       | true             |
      | oro_checkout.group_line_items_by             | product.category |
      | oro_checkout.create_suborders_for_each_group | true             |

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
    When I click "Continue"
    Then Page title equals to "Payment - Checkout"
    And I should see "Stripe payment dialog will be opened after the final step of the checkout." in the "Stripe Payment Element Method On MultiStep Checkout" element
    When I check "Stripe Payment Element" on the checkout page
    And I click "Continue"
    Then Page title equals to "Order Review - Checkout"

  Scenario: 
    When I click "Expand Checkout Footer"
    Then I should see Checkout Totals with data:
      | Subtotal | $70.00 |
      | Shipping | $6.00  |
    And should see "Total: $76.00"
    And I should see "Lighting Products" in the "First Checkout Shipping Grid Title" element
    And I should see following "First Checkout Shipping Grid" grid:
      | SKU      | Product            | Qty | Price  | Subtotal |
      | LIGHT001 | Lighting Product 1 | 2   | $10.00 | $20.00   |
    And records in "First Checkout Shipping Grid" should be 1
    And I should see "Phones" in the "Second Checkout Shipping Grid Title" element
    And I should see following "Second Checkout Shipping Grid" grid:
      | SKU      | Product         | Qty | Price  | Subtotal |
      | PHONE001 | Phone Product 1 | 1   | $20.00 | $20.00   |
      | PHONE002 | Phone Product 2 | 2   | $15.00 | $30.00   |
    And records in "Second Checkout Shipping Grid" should be 2
    When I click "Submit Order"
    And I fill "MultiStep Checkout Stripe Payment Element Form" with:
      | Stripe Card Number | 4242 4242 4242 4242 |
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
    And I should see "Payment Status Paid in full"

  Scenario: Check sub-orders on storefront order history page
    When I click "Account Dropdown"
    And I click "Order History"
    And I show column "Payment Method" in "PastOrdersGrid" frontend grid
    And I show column "Total" in "PastOrdersGrid" frontend grid
    And I show column "Shipping Method" in "PastOrdersGrid" frontend grid
    Then I should see following "PastOrdersGrid" grid:
      | Total  | Shipping Method | Payment Method         | Payment Status |
      | $53.00 | Flat Rate       | Stripe Payment Element | Paid in full   |
      | $23.00 | Flat Rate       | Stripe Payment Element | Paid in full   |
      | $76.00 | Multi Shipping  | Stripe Payment Element | Paid in full   |
    And records in "PastOrdersGrid" should be 3

  Scenario: Check main order details on storefront
    Given I show filter "Order Number" in "PastOrdersGrid" frontend grid
    When I filter Order Number as is equal to "1" in "PastOrdersGrid"
    And I click view "1" in grid
    Then I should see "Subtotal $70.00" in the "Subtotals" element
    And I should see "Shipping $6.00" in the "Subtotals" element
    And I should see "Total $76.00" in the "Subtotals" element

  Scenario: Check first sub-order details on storefront
    When I open Order History page on the store frontend
    And I filter Order Number as contains "-1" in "PastOrdersGrid"
    And I click view "-1" in grid
    Then I should see "Subtotal $20.00" in the "Subtotals" element
    And I should see "Shipping $3.00" in the "Subtotals" element
    And I should see "Total $23.00" in the "Subtotals" element

  Scenario: Check second sub-order details on storefront
    When I open Order History page on the store frontend
    And I filter Order Number as contains "-2" in "PastOrdersGrid"
    And I click view "-2" in grid
    Then I should see "Subtotal $50.00" in the "Subtotals" element
    And I should see "Shipping $3.00" in the "Subtotals" element
    And I should see "Total $53.00" in the "Subtotals" element

  Scenario: Check Payment Method and Payment Status on orders in back-office
    Given I proceed as the Admin
    And I login as administrator
    When I go to Sales/ Orders
    And I show column Payment Method in grid
    And I show column Total in grid
    And I show column Currency in grid
    And I sort grid by "Order Number"
    Then I should see following grid:
      | Total  | Currency | Payment Method         | Payment Status |
      | $76.00 | USD      | Stripe Payment Element | Paid in full   |
      | $23.00 | USD      | Stripe Payment Element | Paid in full   |
      | $53.00 | USD      | Stripe Payment Element | Paid in full   |
    And number of records should be 3

  Scenario: Check Payment Method and Payment Status on main order back-office page
    Given I show filter "Order Number" in grid
    When I filter Order Number as is equal to "1"
    And I click view "$76.00" in grid
    Then I should see order with:
      | Payment Method | Stripe Payment Element |
      | Payment Status | Paid in full           |

  Scenario: Check Payments section on main order back-office page
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type     | Amount | Successful |
      | Stripe Payment Element | Purchase | $76.00 | Yes        |

  Scenario: Check Payment Method and Payment Status on first sub-order back-office page
    When I go to Sales/ Orders
    And I filter Order Number as contains "-1"
    And I click view "$23.00" in grid
    Then I should see order with:
      | Payment Method | Stripe Payment Element |
      | Payment Status | Paid in full           |

  Scenario: Check Payments section on first sub-order back-office page
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type   | Amount | Successful |
      | Stripe Payment Element | Charge | $23.00 | Yes        |
    And I should see following actions for Stripe Payment Element in "Order Payment Transaction Grid":
      | Refund |

  Scenario: Check Payment Method and Payment Status on second sub-order back-office page
    When I go to Sales/ Orders
    And I filter Order Number as contains "-2"
    And I click view "$53.00" in grid
    Then I should see order with:
      | Payment Method | Stripe Payment Element |
      | Payment Status | Paid in full           |

  Scenario: Check Payments section on second sub-order back-office page
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type   | Amount | Successful |
      | Stripe Payment Element | Charge | $53.00 | Yes        |
    And I should see following actions for Stripe Payment Element in "Order Payment Transaction Grid":
      | Refund |
