@regression
@behat-test-env
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__authorize.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml
@fixture-OroStripePaymentBundle:OrderPayment/stripe_payment_element__order_cancel_payment.yml
@fixture-OroStripePaymentBundle:OrderPayment/stripe_payment_element__order_cancel_payment__transactions.yml

Feature: Stripe Payment Element - Order Cancel Payment

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
      | Total      | Currency | Payment Method         | Payment Status     |
      | $12,345.67 | USD      | Stripe Payment Element | Payment authorized |

  Scenario: Check Payments section on order back-office page
    When I click view Stripe Payment Element in grid
    Then I should see order with:
      | Payment Method | Stripe Payment Element |
      | Payment Status | Payment authorized     |
    And I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type      | Amount     | Successful |
      | Stripe Payment Element | Authorize | $12,345.67 | Yes        |
    And I should see following actions for Stripe Payment Element in "Order Payment Transaction Grid":
      | Capture              |
      | Cancel Authorization |

  Scenario: Cancel the payment
    When I click "Cancel" on row "Stripe Payment Element" in grid "Order Payment Transaction Grid"
    Then I should see "Cancel Authorization" in the "UiDialog Title" element
    And should see "The $12,345.67 payment will be canceled. Are you sure you want to continue?" in the "UiDialog" element
    And I fill "Stripe Cancel Transaction Form" with:
      | Notes               | By client request   |
      | Cancellation reason | Request By Customer |
    And I should see the following options for "Cancellation reason" select:
      | Request By Customer |
      | Duplicate           |
      | Fraudulent          |
      | Abandoned           |
    When I click "Yes, Cancel Authorization"
    Then I should see "The payment of $12,345.67 has been canceled successfully." flash message

  Scenario: Check cancel transaction in Payments section on order back-office page
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type      | Amount     | Successful |
      | Stripe Payment Element | Cancel    | $12,345.67 | Yes        |
      | Stripe Payment Element | Authorize | $12,345.67 | Yes        |
    And I should not see following actions for Stripe Payment Element in "Order Payment Transaction Grid":
      | Capture |
      | Refund  |
      | Cancel  |

  Scenario: Check Payment Status column on orders back-office page
    When I go to Sales/ Orders
    And I show column Payment Method in grid
    And I show column Total in grid
    And I show column Currency in grid
    Then I should see following grid:
      | Total      | Currency | Payment Method         | Payment Status   |
      | $12,345.67 | USD      | Stripe Payment Element | Payment canceled |

