@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroPaymentBundle:ProductsAndShoppingListsForPayments.yml
Feature: Stripe integration single page checkout
    In order for the admin to be able to manage integration
    As an Admin
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
        And I select "Stripe (Legacy)" from "Type"
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

    Scenario: Create second Stripe Integration
        Given I go to System/Integrations/Manage Integrations
        And I click "Create Integration"
        And I select "Stripe (Legacy)" from "Type"
        And I fill "Stripe Form" with:
            | Name                   | Stripe2  |
            | Label                  | Stripe2  |
            | Short Label            | Stripe2  |
            | API Public Key         | pk_test  |
            | API Secret Key         | sk_test  |
            | Webhook Signing Secret | w_secret |
        And I save and close form
        Then I should see "Integration saved" flash message
        And I should see Stripe2 in grid

    Scenario: Create Stripe payment rule
        And I create payment rule with "Stripe" payment method
        And I go to System/Payment Rules
        And I should see StripePaymentRule in grid

    Scenario: Create second Stripe payment rule
        And I create payment rule with "Stripe2" payment method
        And I go to System/Payment Rules
        And I should see Stripe2PaymentRule in grid

    Scenario: Activate Single Page Checkout workflow
        And I activate "Single Page Checkout" workflow

    Scenario: Checkout with failed stripe payment
        Given I proceed as the Buyer
        And I signed in as AmandaRCole@example.org on the store frontend
        When I open page with shopping list List 1
        And I click "Create Order"
        And I select "ORO, Fifth avenue, 10115 Berlin, Germany" from "Billing Address"
        And I select "ORO, Fifth avenue, 10115 Berlin, Germany" from "Shipping Address"
        And I check "Flat Rate" on the checkout page
        # Test card number was taken from https://stripe.com/docs/
        And I fill "Stripe Card Form Single Page" with:
            | Stripe Card Number | 4000 0000 0000 9235 |
            | Stripe Exp Date    | 12 / 35             |
            | Stripe CVC         | 111                 |
            | Stripe ZIP         | 12345               |
        And I click "Submit Order"
        And I should see "We were unable to process your payment. Please verify your payment information and try again" flash message

    Scenario: Payment details in the Stripe form don't reset after partial page reload e.g. when selecting address
        Given I fill "Stripe Card Form Single Page" with:
            | Stripe Card Number | 4242 4242 4242 4242 |
            | Stripe Exp Date    | 12 / 35             |
            | Stripe CVC         | 111                 |
            | Stripe ZIP         | 12345               |
        When I check "Use billing address" on the checkout page
        Then "Stripe Card Form Single Page" must contain values:
            | Stripe Card Number | 4242 4242 4242 4242 |
            | Stripe Exp Date    | 12 / 35             |
            | Stripe CVC         | 111                 |
            | Stripe ZIP         | 12345               |
        And I select "ORO, Fifth avenue, 10115 Berlin, Germany" from "Billing Address"

    Scenario: Checkout with success stripe payment
        When I click "Submit Order"
        Then I see the "Thank You" page with "Thank You For Your Purchase!" title

    Scenario: Checkout with second stripe integration
        Given I open page with shopping list List 2
        And I click "Create Order"
        And I select "ORO, Fifth avenue, 10115 Berlin, Germany" from "Billing Address"
        And I select "ORO, Fifth avenue, 10115 Berlin, Germany" from "Shipping Address"
        And I check "Flat Rate" on the checkout page
        And I check "Stripe2" on the checkout page
        And I fill "Second Stripe Card Form Single Page" with:
            | Stripe Card Number | 4242 4242 4242 4242 |
            | Stripe Exp Date    | 12 / 35             |
            | Stripe CVC         | 111                 |
            | Stripe ZIP         | 12345               |
        And I click "Submit Order"
        Then I see the "Thank You" page with "Thank You For Your Purchase!" title

    Scenario: Check Order in admin
        Given I proceed as the Admin
        When I go to Sales/Orders
        And I show column Payment Method in grid
        Then number of records should be 2
        And I should see following grid:
            | Payment Status     | Payment Method |
            | Payment authorized | Stripe2        |
            | Payment authorized | Stripe         |
        And filter Order Number as is equal to "2"
        And I click on 2 in grid
        When I click "Payments"
        And I should see following "Order Payment Transaction Grid" grid:
            | Payment Method | Type      | Amount | Successful |
            | Stripe         | Authorize | $13.00 | Yes        |
