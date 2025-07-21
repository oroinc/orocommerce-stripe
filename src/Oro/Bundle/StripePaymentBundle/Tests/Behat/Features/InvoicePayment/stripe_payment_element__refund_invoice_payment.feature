@regression
@behat-test-env
@fixture-OroStripePaymentBundle:stripe_payment_element__refund_invoice_payment.yml
@fixture-OroStripePaymentBundle:stripe_payment_element__refund_invoice_payment__transactions.yml

Feature: Stripe Payment Element - Refund Invoice Payment

  Scenario: Feature Background
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |

  Scenario: Select the payment method for invoices
    Given I proceed as the Admin
    And I login as administrator
    When I go to System/Configuration
    And I follow "Commerce/Sales/Invoices" on configuration sidebar
    And I fill "Configuration Invoice Form" with:
      | Payment Method Use Default | false                  |
      | Payment Method             | Stripe Payment Element |
    And I click "Save settings"
    Then I should see "Configuration saved" flash message

  Scenario: Check Payment Method and Payment Status column on invoices back-office page
    When I go to Sales/Invoices
    And I click View INV-001 in grid
    Then I should see invoice with:
      | Invoice Number | INV-001                |
      | Currency       | USD                    |
      | Total Amount   | $12,345.67             |
      | Payment Method | Stripe Payment Element |
      | Payment Status | Paid in full           |

  Scenario: Check Payments section on invoice back-office page
    Given I should see following "Invoice Payment Transactions Grid" grid:
      | Payment Method         | Type   | Amount     | Successful |
      | Stripe Payment Element | Charge | $12,345.67 | Yes        |
    And I should see following actions for Charge in "Invoice Payment Transactions Grid":
      | Refund |

  Scenario: Refund the payment
    When I click "Refund" on row "Charge" in grid "Invoice Payment Transactions Grid"
    Then I should see "Refund Payment" in the "UiDialog Title" element
    And I should see "The $12,345.67 payment will be refunded. Are you sure you want to continue?"
    And I fill form with:
      | Amount | 12345.67            |
      | Notes  | Refund Payment Note |
    When I click "Yes, Refund Payment" in modal window
    Then I should see "The payment of $12,345.67 has been refunded successfully." flash message

  Scenario: Check refund transaction in Payments section on invoice back-office page
    Given I should see following "Invoice Payment Transactions Grid" grid:
      | Payment Method         | Type   | Amount     | Successful |
      | Stripe Payment Element | Refund | $12,345.67 | Yes        |
      | Stripe Payment Element | Charge | $12,345.67 | Yes        |
    And I should not see following actions for Charge in "Invoice Payment Transactions Grid":
      | Refund |

  Scenario: Check refunded Payment Status column on invoices back-office page
    Given I should see that "Page Title" contains "Refunded"
    And I should see invoice with:
      | Invoice Number | INV-001                |
      | Currency       | USD                    |
      | Total Amount   | $12,345.67             |
      | Payment Method | Stripe Payment Element |
      | Payment Status | Refunded               |
