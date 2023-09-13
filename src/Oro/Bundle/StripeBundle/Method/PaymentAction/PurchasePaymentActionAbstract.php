<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Client\Request\CreateSetupIntentRequest;
use Oro\Bundle\StripeBundle\Client\Request\Factory\CreateCustomerRequestFactory;
use Oro\Bundle\StripeBundle\Client\Response\MultiPurchaseResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Provides common logic for purchase actions.
 */
abstract class PurchasePaymentActionAbstract extends PaymentActionAbstract implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected EntitiesTransactionsProvider $entitiesTransactionsProvider;
    protected PaymentTransactionProvider $paymentTransactionProvider;
    protected CreateCustomerRequestFactory $createCustomerRequestFactory;

    public function __construct(
        StripeGatewayFactoryInterface $clientFactory,
        EntitiesTransactionsProvider $entitiesTransactionsProvider,
        PaymentTransactionProvider $paymentTransactionProvider,
        CreateCustomerRequestFactory $createCustomerRequestFactory
    ) {
        parent::__construct($clientFactory);
        $this->entitiesTransactionsProvider = $entitiesTransactionsProvider;
        $this->paymentTransactionProvider = $paymentTransactionProvider;
        $this->createCustomerRequestFactory = $createCustomerRequestFactory;
        $this->logger = new NullLogger();
    }

    /**
     * Initiate payments for a set of transactions. Send a request to STRIPE service for each transaction separately.
     *
     * @throws \Throwable
     */
    protected function purchaseMultipleTransactions(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $transactions = $this->entitiesTransactionsProvider->getTransactionsForMultipleEntities($paymentTransaction);
        $response = new MultiPurchaseResponse();

        foreach ($transactions as $transaction) {
            try {
                $purchaseResponse = $this->executePurchase($config, $transaction);

                if ($purchaseResponse->isSuccessful()) {
                    $response->setHasSuccessful(true);
                } else {
                    $response->setSuccessful(false);
                }
            } catch (StripeApiException $exception) {
                $this->logger->error($exception->getMessage(), [
                    'error' => $exception->getMessage(),
                    'stripe_error_code' => $exception->getStripeErrorCode(),
                    'decline_code' => $exception->getDeclineCode(),
                    'exception' => $exception
                ]);

                $response->setSuccessful(false);
            }
            $this->paymentTransactionProvider->savePaymentTransaction($transaction);
        }

        return $response;
    }

    /**
     * Create paymentIntent as a storage of customer and attached to it payment method. PaymentIntent could be used for
     * generating payments in the future.
     */
    protected function createPaymentIntent(
        PaymentTransaction $paymentTransaction,
        StripePaymentConfig $config
    ): ResponseObjectInterface {
        $client = $this->getClient($config);

        // Create SetupIntent to use for further payments.
        $setupIntentRequest = new CreateSetupIntentRequest($paymentTransaction);
        $setupIntentResponseObject = $client->createSetupIntent($setupIntentRequest);
        // Add information about SetupIntent and Customer to PaymentTransaction
        $this->updateTransactionOptions(
            $paymentTransaction,
            [$setupIntentResponseObject::SETUP_INTENT_ID_PARAM => $setupIntentResponseObject->getIdentifier()]
        );

        return $setupIntentResponseObject;
    }

    /**
     * Create customer on STRIPE.
     */
    protected function createCustomer(
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
