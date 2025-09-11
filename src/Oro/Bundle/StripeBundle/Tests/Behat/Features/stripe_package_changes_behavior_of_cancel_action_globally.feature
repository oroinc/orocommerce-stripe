@ticket-BB-25652
@regression
@behat-test-env
@fixture-OroFlatRateShippingBundle:FlatRateIntegration.yml
@fixture-OroCheckoutBundle:Shipping.yml
@fixture-OroPaymentBundle:ProductsAndShoppingListsForPayments.yml

Feature: Stripe package changes behavior of cancel action globally
  In order for the user Cancel action will available for
  all payment methods even if Stripe isn’t configured for the order

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

  Scenario: Create new Apruve integration
    Given I go to System/ Integrations/ Manage Integrations
    And I click "Create Integration"
    When I fill "Apruve Integration Form" with:
      | Type          | Apruve                           |
      | Name          | Apruve                           |
      | Label         | Apruve                           |
      | Short Label   | Apruve Short Label               |
      | Test Mode     | True                             |
      | API Key       | d0cbaf64fccdf9de4209895b0f8404ab |
      | Merchant ID   | 507c64f0cbcf190ce548d19e93d5c909 |
      | Status        | Active                           |
      | Default owner | John Doe                         |
    And I click "Check Apruve connection"
    And I should see "Apruve Connection is valid" flash message
    And I save and close form
    Then I should see "Integration saved" flash message
    And I should see Apruve in grid

  Scenario: Create payment rules for OroPay and Stripe integrations
    Given I create payment rule with "Apruve" payment method
    And I create payment rule with "Stripe" payment method
    When I go to System/Payment Rules
    Then I should see StripePaymentRule in grid
    And I should see ApruvePaymentRule in grid

  Scenario: Checkout with stripe payment
    Given I proceed as the Buyer
    And I signed in as AmandaRCole@example.org on the store frontend
    When I open page with shopping list List 1
    And I click "Create Order"
    And I click "Ship to This Address"
    And I click "Continue"
    And I click "Continue"
    And I scroll to top
    And I click on "Checkout Payment Method" with title "Apruve"
    And I click "Continue"
    And I click "Submit Order"
    Then I see the "Thank You" page with "Thank You For Your Purchase!" title

  Scenario: Check that Cancel Authorization button exists for Authorize on order transactions grid
    Given I proceed as the Admin
    When I go to Sales/Orders
    And I click "View" on first row in grid
    Then I should see following "Order Payment Transactions Grid" grid:
      | Payment Method | Type      | Amount | Successful |
      | Apruve         | Authorize | $13.00 | Yes        |
    And I should see following actions for Apruve in "Order Payment Transactions Grid":
      | Cancel Authorization |
