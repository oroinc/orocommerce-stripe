@regression
@behat-test-env
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__reauthorize.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml
@fixture-OroStripePaymentBundle:OrderPayment/stripe_payment_element__order_reauthorize_payment.yml
@fixture-OroStripePaymentBundle:OrderPayment/stripe_payment_element__order_reauthorize_payment___failing_transactions.yml

Feature: Stripe Payment Element - Order Re-Authorization Failure

  Scenario: Check Payment Method and Payment Status column on orders back-office page
    Given I login as administrator
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
      | Re-Authorize Payment |

  Scenario: Re-authorize the payment
    When I click "Re-Authorize" on row "Stripe Payment Element" in grid "Order Payment Transaction Grid"
    Then I should see "Re-Authorize Payment" in the "UiWindow Title" element
    And should see "The payment authorization of $12,345.67 will be renewed. Are you sure you want to continue?" in confirmation dialogue
    When I click "Yes, Re-Authorize"
    Then I should see "Your card was declined." flash message

  Scenario: Check re-authorize transaction in Payments section on order back-office page
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type         | Amount     | Successful |
      | Stripe Payment Element | Authorize    | $12,345.67 | No         |
      | Stripe Payment Element | Cancel       | $12,345.67 | No         |
      | Stripe Payment Element | Re-Authorize | $12,345.67 | No         |
      | Stripe Payment Element | Authorize    | $12,345.67 | Yes        |
    And I should see following actions for Stripe Payment Element in "Order Payment Transaction Grid":
      | Capture              |
      | Re-Authorize Payment |

  Scenario: Check Payment Status remains authorized on orders back-office page
    When I go to Sales/ Orders
    And I show column Payment Method in grid
    And I show column Total in grid
    And I show column Currency in grid
    Then I should see following grid:
      | Total      | Currency | Payment Method         | Payment Status     |
      | $12,345.67 | USD      | Stripe Payment Element | Payment authorized |
