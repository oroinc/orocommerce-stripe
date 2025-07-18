@fixture-OroStripePaymentBundle:stripe_payment_element__configure_invoice_payment.yml

Feature: Stripe Payment Element - Configure Invoice Payment

  Scenario: Feature Background
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |

  Scenario: Create new Stripe Payment Element integration
    Given I proceed as the Admin
    And I login as administrator
    When I go to System/Integrations/Manage Integrations
    And I click "Create Integration"
    And I select "Stripe Payment Element" from "Type"
    And I fill "Configuration Stripe Payment Element Form" with:
      | Name                                  | Stripe Payment Element |
      | API Public Key                        | pk_test                |
      | API Secret Key                        | sk_test                |
      | Payment Method Name                   | Stripe Payment Element |
      | Payment Method Label                  | Stripe Payment Element |
      | Payment Method Short Label            | Stripe Payment Element |
      | Create Webhook Endpoint Automatically | false                  |
    And I fill "Configuration Stripe Payment Element Form" with:
      | Webhook Signing Secret | w_secret |
    And I save and close form
    Then I should see "Integration saved" flash message
    And I should see Stripe Payment Element in grid

  Scenario: Select the payment method for invoices
    When I go to System/Configuration
    And I follow "Commerce/Sales/Invoices" on configuration sidebar
    And I fill "Configuration Invoice Form" with:
      | Payment Method Use Default | false                  |
      | Payment Method             | Stripe Payment Element |
    And I click "Save settings"
    Then I should see "Configuration saved" flash message

  Scenario: Check invoice payment page is available
    Given I operate as the Buyer
    And I signed in as AmandaRCole@example.org on the store frontend
    When I click "Account Dropdown"
    And I click "Invoices"
    And I click View INV-001 in grid
    And click on "Invoice Pay Button"
    Then Page title equals to "Payment - Invoice #INV-001 - View - Invoices - My Account"
    And I should see "Payment - Invoice #INV-001"
    And I should see "Total: $12,345.67"
    And I should see a "Invoice Stripe Payment Element Form" element
    And I should see "Proceed with Payment"
    And I should see "Back to Invoice Page"
