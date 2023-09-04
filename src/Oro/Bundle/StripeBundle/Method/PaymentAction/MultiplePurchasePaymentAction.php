<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Response\SetupIntentResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Implements logic to save payment details and use them for further purchase actions.
 */
class MultiplePurchasePaymentAction extends PurchasePaymentActionAbstract implements PaymentActionInterface
{
    /**
     * SetupIntent flow is used as payment method storage and could be used in future to generate
     * PaymentIntent for each related transaction.
     */
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $client = $this->getClient($config);
        $this->createCustomer($paymentTransaction, $client);

        $setupIntentResponseObject = $this->createPaymentIntent($paymentTransaction, $config);
        $response = new SetupIntentResponse($setupIntentResponseObject);

        // Update transaction data.
        $this->updateTransactionData($paymentTransaction, $setupIntentResponseObject, $response->isSuccessful());

        $paymentTransaction->setActive(true);
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
}
