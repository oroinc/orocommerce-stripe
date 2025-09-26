@regression
@behat-test-env
@fixture-OroStripePaymentBundle:stripe_payment_element__invoice_amounts_below_the_supported_threshold.yml

Feature: Stripe Payment Element - invoice amounts below the supported threshold

  Scenario: Feature Background
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |
    And I change configuration options:
      | oro_invoice.invoice_feature_enabled                   | true |
      | oro_commerce_invoice.commerce_invoice_feature_enabled | true |

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

  Scenario: Buyer cannot pay invoice below supported threshold
    Given I operate as the Buyer
    And I signed in as AmandaRCole@example.org on the store frontend
    When I click "Account Dropdown"
    And I click "Invoices"
    Then I should see following grid:
      | Invoice Number | Total Amount | Payment Status  |
      | INV-001        | $0.05        | Pending payment |
    And I should see following actions for INV-001 in grid:
      | View |
      | Pay  |
    When I click View INV-001 in grid
    And click on "Invoice Pay Button"
    Then I should see "No payment methods are available, please contact us to proceed."
