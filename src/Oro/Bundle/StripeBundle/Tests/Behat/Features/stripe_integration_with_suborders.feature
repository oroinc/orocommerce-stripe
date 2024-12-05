@ticket-STRIPE-49
@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroPaymentTermBundle:PaymentTermIntegration.yml
@fixture-OroCheckoutBundle:Payment.yml
@fixture-OroCheckoutBundle:CheckoutCustomerFixture.yml
@fixture-OroCheckoutBundle:ProductsAndCategoriesForMultiShippingFixture.yml
@fixture-OroCheckoutBundle:ShoppingListForMultiShippingFixture.yml

Feature: Stripe integration with suborders
    In order for the admin to be able to use Stripe payment
    As an Admin
    I want to have the ability to use Stripe payment for sub orders

    Scenario: Feature Background
        Given sessions active:
            | Admin | first_session  |
            | Buyer | second_session |
        And I change configuration options:
            | oro_checkout.enable_shipping_method_selection_per_line_item | true             |
            | oro_checkout.enable_line_item_grouping                      | true             |
            | oro_checkout.group_line_items_by                            | product.category |
            | oro_checkout.create_suborders_for_each_group                | true             |

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

    Scenario: Checkout with stripe payment
        Given I proceed as the Buyer
        And I signed in as AmandaRCole@example.org on the store frontend
        When I open page with shopping list List 1
        And I click "Create Order"
        And I click "Ship to This Address"
        And I click "Continue"
        And I click "Continue"
        And I scroll to top
        And I click on "Checkout Payment Method" with title "Stripe"
        # Test card number was taken from https://stripe.com/docs/
        And I fill "Stripe Card Form" with:
            | Stripe Card Number | 4242 4242 4242 4242 |
            | Stripe Exp Date    | 12 / 35             |
            | Stripe CVC         | 111                 |
            | Stripe ZIP         | 12345               |
        And I click "Continue"
        And I click "Submit Order"
        Then I see the "Thank You" page with "Thank You For Your Purchase!" title

    Scenario: Capture Sub Order in admin
        Given I proceed as the Admin
        When I go to Sales/Orders
        And I show column Payment Method in grid
        Then number of records should be 3
        And I sort grid by "Order Number"
        Then I should see following grid:
            | Order Number | Payment Status     | Payment Method |
            | 1            | Payment authorized | Stripe         |
            | 1-1          | Payment authorized | Stripe         |
            | 1-2          | Payment authorized | Stripe         |
        And I click on 1 in grid
        When I click "Payments"
        Then I should see following "Order Payment Transaction Grid" grid:
            | Payment Method | Type     | Amount | Successful |
            | Stripe         | Purchase | $59.00 | Yes        |
        When I click "Payments"
        Then I should see following "First Sub Order Payment Transaction Grid" grid:
            | Payment Method | Type      | Amount | Successful |
            | Stripe         | Authorize | $13.00 | Yes        |
        When I click "Sub-Order #1-2"
        Then I should see following "Second Sub Order Payment Transaction Grid" grid:
            | Payment Method | Type      | Amount | Successful |
            | Stripe         | Authorize | $46.00 | Yes        |
        When I click "Sub-Order #1-1"
        When I click "Capture" on row "Authorize" in grid "First Sub Order Payment Transaction Grid"
        Then I should see "Charge The Customer" in the "UiWindow Title" element
        When I click "Yes, Charge" in modal window
        Then I should see "The payment of $13.00 has been captured successfully" flash message
        When I click "Payments"
        Then I should see following "First Sub Order Payment Transaction Grid" grid:
            | Payment Method | Type      | Amount | Successful |
            | Stripe         | Capture   | $13.00 | Yes        |
            | Stripe         | Authorize | $13.00 | Yes        |

    Scenario: Refund Sub Order in admin
        When I click "Refund" on row "Capture" in grid "First Sub Order Payment Transaction Grid"
        Then I should see "Refund Payment" in the "UiDialog Title" element
        And I should see "The $13.00 payment will be refunded. Are you sure you want to continue?"
        And I fill form with:
            | Amount | 5.00                |
            | Notes  | Refund Payment Note |
        And I click "Yes, Refund Payment" in modal window
        Then I should see "The payment of $5.00 has been refunded successfully." flash message
        When I click "Refund" on row "Capture" in grid "First Sub Order Payment Transaction Grid"
        Then I should see "Refund Payment" in the "UiDialog Title" element
        And I should see "The $8.00 payment will be refunded. Are you sure you want to continue?"
        And I fill form with:
            | Amount | 8.00                        |
            | Notes  | Another Refund Payment Note |
        And I click "Yes, Refund Payment" in modal window
        Then I should see "The payment of $8.00 has been refunded successfully." flash message
        When I click "Sub-Orders"
        And I sort "SubOrders Grid" by "Order Number"
        And I click on 1-1 in grid "SubOrders Grid"
        And I click "Activity"
        Then I should see "Payment refund was initiated. Notes: Refund Payment Note"
        And I should see "Payment refund was initiated. Notes: Another refund Payment Note"
        When I click "Payments"
        Then I should see following "Order Payment Transaction Grid" grid:
            | Payment Method | Type      | Amount | Successful |
            | Stripe         | Refund    | $8.00  | Yes        |
            | Stripe         | Refund    | $5.00  | Yes        |
            | Stripe         | Capture   | $13.00 | Yes        |
            | Stripe         | Authorize | $13.00 | Yes        |

    Scenario: Cancel Authorization for Sub Order in admin
        Given I go to Sales/Orders
        And I sort grid by "Order Number"
        And I click on 1 in grid
        And I click "Payments"
        And I click "Sub-Order #1-2"
        When I click "Cancel Authorization" on row "Authorize" in grid "Second Sub Order Payment Transaction Grid"
        Then I should see "Cancel Authorization" in the "UiDialog Title" element
        And I should see "The $46.00 payment will be cancelled. Are you sure you want to continue?"
        And I fill form with:
            | Notes | Cancel Authorization Note |
        And I click "Yes, Cancel Authorization" in modal window
        Then I should see "The payment of $46.00 has been canceled successfully." flash message
        And I should see following "SubOrders Grid" grid:
            | Order Number | Total  | Payment Status   |
            | 1-2          | $46.00 | Payment canceled |
            | 1-1          | $13.00 | Refunded         |
        When I click on Payment canceled in grid "SubOrders Grid"
        And I click "Activity"
        Then I should see "Payment authorization hold was cancelled. Notes: Cancel Authorization Note"
        When I click "Payments"
        Then I should see following "Order Payment Transaction Grid" grid:
            | Payment Method | Type      | Amount | Successful |
            | Stripe         | Cancel    | $46.00 | Yes        |
            | Stripe         | Authorize | $46.00 | Yes        |
