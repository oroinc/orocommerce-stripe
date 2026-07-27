@regression
@behat-test-env
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__charge.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml
@fixture-OroStripePaymentBundle:OrderPayment/stripe_payment_element__order_refund_payment.yml
@fixture-OroStripePaymentBundle:OrderPayment/stripe_payment_element__order_refund_payment__transactions.yml

Feature: Stripe Payment Element - Order Refund Payment

  Scenario: Feature Background
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |

  Scenario: Check Payment Method and Payment Status column on orders back-office page
    Given I proceed as the Admin
    And I login as administrator
    When I go to Sales/ Orders
    And I show column Payment Method in grid
    And I show column Total in grid
    And I show column Currency in grid
    Then I should see following grid:
      | Total      | Currency | Payment Method         | Payment Status |
      | $12,345.67 | USD      | Stripe Payment Element | Paid in full   |

  Scenario: Check Payments section on order back-office page
    When I click view Stripe Payment Element in grid
    Then I should see order with:
      | Payment Method | Stripe Payment Element |
      | Payment Status | Paid in full           |
    And I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type   | Amount     | Successful |
      | Stripe Payment Element | Charge | $12,345.67 | Yes        |
    And I should see following actions for Stripe Payment Element in "Order Payment Transaction Grid":
      | Refund |

  Scenario: Refund the payment
    When I click "Refund" on row "Stripe Payment Element" in grid "Order Payment Transaction Grid"
    Then I should see "Refund Payment" in the "UiDialog Title" element
    And I fill form with:
      | Amount         | 12345.67            |
      | Notes          | By client request   |
      | Refund Reason | Request By Customer |
    And I should see the following options for "Refund Reason" select:
      | Request By Customer |
      | Duplicate           |
      | Fraudulent          |
    And I click "Yes, Refund Payment"
    Then I should see "The payment of $12,345.67 has been refunded successfully." flash message

  Scenario: Check refund transaction in Payments section on order back-office page
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type   | Amount     | Successful |
      | Stripe Payment Element | Refund | $12,345.67 | Yes        |
      | Stripe Payment Element | Charge | $12,345.67 | Yes        |

  Scenario: Check refunded Payment Status column on orders back-office page
    When I go to Sales/ Orders
    And I show column Payment Method in grid
    And I show column Total in grid
    And I show column Currency in grid
    Then I should see following grid:
      | Total      | Currency | Payment Method         | Payment Status |
      | $12,345.67 | USD      | Stripe Payment Element | Refunded       |
