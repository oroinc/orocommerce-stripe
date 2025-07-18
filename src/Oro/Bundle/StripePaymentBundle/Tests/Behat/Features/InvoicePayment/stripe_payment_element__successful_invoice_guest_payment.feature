@regression
@behat-test-env
@fixture-OroStripePaymentBundle:stripe_payment_element__successful_invoice_guest_payment.yml

Feature: Stripe Payment Element - Successful Invoice Guest Payment

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

  Scenario: Remember invoice payment storefront guest URL
    Given I operate as the Buyer
    And I signed in as AmandaRCole@example.org on the store frontend
    And I click "Account Dropdown"
    And I click "Invoices"
    When I click View INV-001 in grid
    And click on "Invoice Guest Payment Link"
    And I remember current URL
    And I click "Account Dropdown"
    Then click "Sign Out"

  Scenario: Check guest payment page
    When I follow remembered URL
    Then Page title equals to "Payment - Invoice #INV-001"
    And I should not see an "Page Sidebar" element
    And I should see "Invoice #INV-001"

  Scenario: Make successful payment
    When I fill "Invoice Stripe Payment Element Form" with:
      | Stripe Card Number | 4242 4242 4242 4242 |
      | Stripe Exp Date    | 12 / 35             |
      | Stripe CVC         | 111                 |
      | Stripe ZIP         | 12345               |
    Then I click "Proceed with Payment"

  Scenario: Check invoice payment success page
    Given Page title equals to "Payment Successful - Invoice #INV-001"
    And I should see "Payment Successful! Invoice #INV-001 was successfully processed."
    And I should not see "Back to Invoice Page"

  Scenario: Check Payment Method and Payment Status column on invoices back-office page
    Given I proceed as the Admin
    When I go to Sales/Invoices
    And I show column Payment Method in grid
    Then I should see following grid:
      | Invoice Number | Total Amount | Payment Method         | Payment Status |
      | INV-001        | $12,345.67   | Stripe Payment Element | Paid in full   |

  Scenario: Check Payment Method and Payment Status on invoice back-office page
    When I click View INV-001 in grid
    Then I should see that "Page Title" contains "Paid in full"
    And I should see invoice with:
      | Invoice Number | INV-001                |
      | Currency       | USD                    |
      | Total Amount   | $12,345.67             |
      | Payment Method | Stripe Payment Element |
      | Payment Status | Paid in full           |

  Scenario: Check Payments section on invoice back-office page
    Given I should see following "Invoice Payment Transactions Grid" grid:
      | Payment Method         | Type   | Amount     | Successful |
      | Stripe Payment Element | Charge | $12,345.67 | Yes        |
    And I should see following actions for Stripe Payment Element in "Invoice Payment Transactions Grid":
      | Refund |

