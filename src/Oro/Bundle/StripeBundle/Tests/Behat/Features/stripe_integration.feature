@regression
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroPaymentBundle:ProductsAndShoppingListsForPayments.yml

Feature: Stripe integration
    In order admin to able manage integration
    As a Admin
    I want to have the ability to create order and manage integration

    Scenario: Feature Background
        Given sessions active:
            | Admin | first_session  |
            | Buyer | second_session |

    Scenario: Create new Stripe Integration
        Given I proceed as the Admin
        And I login as administrator
        And I go to System/Integrations/Manage Integrations
        And I click "Create Integration"
        And I select "Stripe" from "Type"
        # Public Key and Secret Key were taken for testing from https://stripe.com/docs/
        And I fill "Stripe Form" with:
            | Name                   | Stripe   |
            | Label                  | Stripe   |
            | Short Label            | Stripe   |
            | API Public Key         | pk_test  |
            | API Secret Key         | sk_test  |
            | Webhook Signing Secret | w_secret |
        And I save and close form
        Then I should see "Integration saved" flash message
        And I should see Stripe in grid

    Scenario: Create Stripe payment rule
        And I create payment rule with "Stripe" payment method
        And I go to System/Payment Rules
        And I should see StripePaymentRule in grid

    Scenario: Checkout with failed stripe payment
        Given I proceed as the Buyer
        And I signed in as AmandaRCole@example.org on the store frontend
        When I open page with shopping list List 1
        And I wait line items are initialized
        And I click "Create Order"
        And I click "Ship to This Address"
        And I click "Continue"
        And I click "Continue"
        # Test card number was taken from https://stripe.com/docs/
        And I fill "Stripe Card Form" with:
            | Stripe Card Number | 4000 0000 0000 9235 |
            | Stripe Exp Date    | 12 / 35             |
            | Stripe CVC         | 111                 |
            | Stripe ZIP         | 12345               |
        And I click "Continue"
        And I click "Submit Order"
        And I should see "Payment method"
        And I should see "We were unable to process your payment. Please verify your payment information and try again." flash message

    Scenario: Checkout with manual stripe payment
        And I fill "Stripe Card Form" with:
            | Stripe Card Number | 4242 4242 4242 4242 |
            | Stripe Exp Date    | 12 / 35             |
            | Stripe CVC         | 111                 |
            | Stripe ZIP         | 12345               |
        And I click "Continue"
        And I click "Submit Order"
        Then I see the "Thank You" page with "Thank You For Your Purchase!" title

    Scenario: Capture Order in admin
        Given I proceed as the Admin
        And I go to Sales/Orders
        Then number of records should be 1
        Then I should see following grid:
            | Payment Status     | Payment Method |
            | Payment authorized | Stripe         |
        And I click on 1 in grid
        When I click "Payment History"
        And I should see following "Order Payment Transaction Grid" grid:
            | Payment Method | Type      | Amount | Successful |
            | Stripe         | Authorize | $13.00 | Yes        |
        When I click "Capture"
        Then I should see "Charge The Customer" in the "UiWindow Title" element
        When I click "Yes, Charge" in modal window
        Then I should see "The payment of $13.00 has been captured successfully" flash message

    Scenario: Edit Stripe Integration
        And I go to System/Integrations/Manage Integrations
        And I click Edit "Stripe" in grid
        And I fill "Stripe Form" with:
            | Payment Action  | automatic |
            | User Monitoring | false     |
        And I save and close form
        Then I should see "Integration saved" flash message

    Scenario: Checkout with automatic stripe payment
        Given I proceed as the Buyer
        When I open page with shopping list List 2
        And I wait line items are initialized
        And I click "Create Order"
        And I click "Ship to This Address"
        And I click "Continue"
        And I click "Continue"
        And I fill "Stripe Card Form" with:
            | Stripe Card Number | 4242 4242 4242 4242 |
            | Stripe Exp Date    | 12 / 35             |
            | Stripe CVC         | 111                 |
            | Stripe ZIP         | 12345               |
        And I click "Continue"
        And I click "Submit Order"
        Then I see the "Thank You" page with "Thank You For Your Purchase!" title

    Scenario: Check Order with automatic payment in admin
        Given I proceed as the Admin
        And I go to Sales/Orders
        Then number of records should be 2
        Then I should see following grid:
            | Payment Status | Payment Method |
            | Paid in full   | Stripe         |
            | Paid in full   | Stripe         |
        And filter Order Number as is equal to "3"
        And I click on 3 in grid
        When I click "Payment History"
        And I should see following "Order Payment Transaction Grid" grid:
            | Payment Method | Type    | Amount | Successful |
            | Stripe         | Capture | $13.00 | Yes        |
