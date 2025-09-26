@regression
@behat-test-env
@fixture-OroStripePaymentBundle:stripe_payment_element__successful_invoice_guest_payment.yml

Feature: Stripe Payment Element - Successful Invoice Guest Payment from back-office

  Scenario: Enable Invoices feature via configuration
    Given I change configuration options:
      | oro_invoice.invoice_feature_enabled | true |

  Scenario: Select the payment method for invoices
    Given  I login as administrator
    When I go to System/Configuration
    And I follow "Commerce/Sales/Invoices" on configuration sidebar
    And I fill "Configuration Invoice Form" with:
      | Payment Method Use Default | false                  |
      | Payment Method             | Stripe Payment Element |
    And I click "Save settings"
    Then I should see "Configuration saved" flash message

  Scenario: Check guest payment page
    When I go to Sales/Invoices
    And I click View INV-001 in grid
    And click on "Invoice Guest Payment Link"
    Then a new browser tab is opened and I switch to it
    And Page title equals to "Payment - Invoice #INV-001"
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
