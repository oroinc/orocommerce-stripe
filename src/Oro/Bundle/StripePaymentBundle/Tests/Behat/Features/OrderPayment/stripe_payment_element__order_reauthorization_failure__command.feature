@regression
@behat-test-env
@fixture-OroStripePaymentBundle:stripe_payment_element_integration__reauthorize.yml
@fixture-OroStripePaymentBundle:stripe_payment_element_payment_rule.yml
@fixture-OroStripePaymentBundle:OrderPayment/stripe_payment_element__order_reauthorize_payment.yml
@fixture-OroStripePaymentBundle:OrderPayment/stripe_payment_element__order_reauthorize_payment__old_failing_transactions.yml

Feature: Stripe Payment Element - Order Re-Authorization Failure - Command

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

  Scenario: Re-authorize the payment via Symfony command and check re-authorization failure email
    When I run Symfony "oro:cron:stripe-payment:re-authorize" command in "behat_test" environment
    Then Email should contains the following:
      | To      | email@example.org                                                             |
      | Subject | Automatic Payment Re-Authorization Failed                                     |
      | Body    | We have not been able to renew the authorization hold of $12,345.67 for Order |
      | Body    | Reason: Your card was declined.                                               |

  Scenario: Check re-authorize transaction in Payments section on order back-office page
    Given I should see following "Order Payment Transaction Grid" grid:
      | Payment Method         | Type      | Amount     | Successful |
      | Stripe Payment Element | Authorize | $12,345.67 | Yes        |
    And number of records in "Order Payment Transaction Grid" grid should be 1
    When I refresh "Order Payment Transaction Grid" grid
    Then I should see following "Order Payment Transaction Grid" grid:
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
