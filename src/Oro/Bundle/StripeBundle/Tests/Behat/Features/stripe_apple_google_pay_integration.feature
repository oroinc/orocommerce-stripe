@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroPaymentBundle:ProductsAndShoppingListsForPayments.yml

Feature: Stripe Apple Google Pay integration
    In order for the admin to be able to manage Apple Pay/Google Pay integration
    As an Admin
    I want to have the ability to create order and manage Apple Pay/Google Pay integration

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

    Scenario: Add Apple Pay/Google Pay payment method
        Given I click edit "StripePaymentRule" in grid
        And I fill "Payment Rule Form" with:
            | Method | Stripe Apple Pay/Google Pay |
        And I save and close form
        Then I should see "Payment rule has been saved" flash message
        And should see Payment Rule with:
            | Name                    | StripePaymentRule                  |
            | Enabled                 | Yes                                |
            | Sort Order              | 1                                  |
            | Currency                | USD                                |
            | Payment Methods Configs | Stripe Stripe Apple Pay/Google Pay |

    Scenario: Checkout with Google Pay
        Given I proceed as the Buyer
        And I signed in as AmandaRCole@example.org on the store frontend
        When I open page with shopping list List 1
        And I wait line items are initialized
        And I click "Create Order"
        And I click "Ship to This Address"
        And I click "Continue"
        When I click "Continue"
        Then I should see a "Google Pay Method First And Selected" element
        And I click "Continue"
        And I click "Submit Order"
        Then I see the "Thank You" page with "Thank You For Your Purchase!" title

    Scenario: Checkout with regular Stripe payment with Apple Pay/Google Pay integration enabled
        Given I open page with shopping list List 2
        And I wait line items are initialized
        And I click "Create Order"
        And I click "Ship to This Address"
        And I click "Continue"
        And I click "Continue"
        And I click on "Checkout Payment Method" with title "Stripe"
        And I fill "Stripe Card Form" with:
            | Stripe Card Number | 4242 4242 4242 4242 |
            | Stripe Exp Date    | 12 / 35             |
            | Stripe CVC         | 111                 |
            | Stripe ZIP         | 12345               |
        And I click "Continue"
        And I click "Submit Order"
        Then I see the "Thank You" page with "Thank You For Your Purchase!" title
