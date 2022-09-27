<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\Request\CreateSetupIntentRequest;
use Oro\Bundle\StripeBundle\Client\Request\Factory\CreateCustomerRequestFactory;
use Oro\Bundle\StripeBundle\Client\Response\SetupIntentResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;

/**
 * Implements logic to save payment details and use them for further purchase actions.
 */
class MultiplePurchasePaymentAction extends PurchasePaymentActionAbstract implements PaymentActionInterface
{
    private CreateCustomerRequestFactory $createCustomerRequestFactory;
    private DoctrineHelper $doctrineHelper;

    public function __construct(
        StripeGatewayFactoryInterface $clientFactory,
        EntitiesTransactionsProvider $entitiesTransactionsProvider,
        PaymentTransactionProvider $paymentTransactionProvider,
        CreateCustomerRequestFactory $createCustomerRequestFactory,
        DoctrineHelper $doctrineHelper
    ) {
        parent::__construct($clientFactory, $entitiesTransactionsProvider, $paymentTransactionProvider);
        $this->createCustomerRequestFactory = $createCustomerRequestFactory;
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * Use SetupIntent flow with is used for payment method storage and could be used in further to generate
     * PaymentIntent for each related transaction.
     *
     * @param StripePaymentConfig $config
     * @param PaymentTransaction $paymentTransaction
     * @return StripeApiResponseInterface
     */
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $client = $this->getClient($config);
        $this->createCustomer($paymentTransaction, $client);

        // Create SetupIntent to use for further payments.
        $setupIntentRequest = new CreateSetupIntentRequest($paymentTransaction);
        $setupIntentResponseObject = $client->createSetupIntent($setupIntentRequest);
        // Add information about SetupIntent and Customer to PaymentTransaction
        $this->updateTransactionOptions(
            $paymentTransaction,
            [$setupIntentResponseObject::SETUP_INTENT_ID_PARAM => $setupIntentResponseObject->getIdentifier()]
        );

        $paymentTransaction->setActive(true);
        $response = new SetupIntentResponse($setupIntentResponseObject);

        // Update transaction data.
        $this->updateTransactionData($paymentTransaction, $setupIntentResponseObject, $response->isSuccessful());
        $this->paymentTransactionProvider->savePaymentTransaction($paymentTransaction);

        // If no additional customer actions needed additional transactions could be created right here.
        if ($setupIntentResponseObject->getStatus() === StripeApiResponseInterface::SUCCESS_STATUS) {
            $response = $this->purchaseMultipleTransactions($config, $paymentTransaction);
        }

        return $response;
    }

    public function isApplicable(string $action, PaymentTransaction $paymentTransaction): bool
    {
        return $action === PaymentMethodInterface::PURCHASE
            && $this->entitiesTransactionsProvider->hasEntities($paymentTransaction);
    }

    /**
     * Create customer on STRIPE.
     */
    private function createCustomer(
        PaymentTransaction $paymentTransaction,
        StripeGatewayInterface $client
    ): void {
        // If customer already set do not create new
        if (!empty($this->getTransactionAdditionalData($paymentTransaction)[CustomerResponse::CUSTOMER_ID_PARAM])) {
            return;
        }

        $createCustomerRequest = $this->createCustomerRequestFactory->create($paymentTransaction);
        $customerResponse = $client->createCustomer($createCustomerRequest);

        $this->updateTransactionOptions(
            $paymentTransaction,
            [CustomerResponse::CUSTOMER_ID_PARAM => $customerResponse->getIdentifier()]
        );
    }
}
