@regression
@behat-test-env
@fixture-OroStripePaymentBundle:stripe_payment_element__declined_invoice_payment.yml

Feature: Stripe Payment Element - Declined Invoice Payment

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

  Scenario: Check invoices storefront page
    Given I operate as the Buyer
    And I signed in as AmandaRCole@example.org on the store frontend
    When I click "Account Dropdown"
    And I click "Invoices"
    And I click on "FrontendGridColumnManagerButton"
    And I click "Select All"
    And I click on "FrontendGridColumnManagerButton"
    Then I should see following grid:
      | Invoice Number | Total Amount | Payment Method | Payment Status  |
      | INV-001        | $12,345.67   |                | Pending payment |
    And I should see following actions for INV-001 in grid:
      | View |
      | Pay  |

  Scenario: Make declined payment
    When I click View INV-001 in grid
    And click on "Invoice Pay Button"
    Then I fill "Invoice Stripe Payment Element Form" with:
      | Stripe Card Number | 4000 0000 0000 9235 |
      | Stripe Exp Date    | 12 / 35             |
      | Stripe CVC         | 111                 |
      | Stripe ZIP         | 12345               |

  Scenario: Check invoice payment error page
    When I click "Proceed with Payment"
    Then Page title equals to "Payment Error - Invoice #INV-001 - View - Invoices - My Account"
    And I should see "Payment Error! Invoice #INV-001"
    And I should see "We are sorry, but your payment could not be processed. Please check your payment details and try again."

  Scenario: Check Payment Method and Payment Status on invoice storefront page
    When I click "Back to Payment Page"
    And I click "Back to Invoice Page"
    Then I should see "Stripe Payment Element"
    And I should see "Payment declined" in the "Invoice Payment Status Label" element
    And I should see a "Invoice Pay Button" element

  Scenario: Check Payment Method and Payment Status column on invoices storefront page
    When I click "Back"
    And I click on "FrontendGridColumnManagerButton"
    And I click "Select All"
    And I click on "FrontendGridColumnManagerButton"
    Then I should see following grid:
      | Invoice Number | Total Amount | Payment Method         | Payment Status   |
      | INV-001        | $12,345.67   | Stripe Payment Element | Payment declined |
    And I should see following actions for INV-001 in grid:
      | View |

  Scenario: Check Payment Method and Payment Status column on invoices back-office page
    Given I proceed as the Admin
    When I go to Sales/Invoices
    And I show column Payment Method in grid
    Then I should see following grid:
      | Invoice Number | Total Amount | Payment Method         | Payment Status   |
      | INV-001        | $12,345.67   | Stripe Payment Element | Payment declined |

  Scenario: Check Payment Method and Payment Status on invoice back-office page
    When I click View INV-001 in grid
    Then I should see that "Page Title" contains "Payment declined"
    And I should see invoice with:
      | Invoice Number | INV-001                |
      | Currency       | USD                    |
      | Total Amount   | $12,345.67             |
      | Payment Method | Stripe Payment Element |
      | Payment Status | Payment declined       |

  Scenario: Check Payments section on invoice back-office page
    Given I should see following "Invoice Payment Transactions Grid" grid:
      | Payment Method         | Type   | Amount     | Successful |
      | Stripe Payment Element | Charge | $12,345.67 | No         |
    And I should not see following actions for Stripe Payment Element in "Invoice Payment Transactions Grid":
      | Refund |

