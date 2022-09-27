<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Client\Response\MultiPurchaseResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;

/**
 * Provides common logic for purchase actions.
 */
abstract class PurchasePaymentActionAbstract extends PaymentActionAbstract
{
    protected EntitiesTransactionsProvider $entitiesTransactionsProvider;
    protected PaymentTransactionProvider $paymentTransactionProvider;

    public function __construct(
        StripeGatewayFactoryInterface $clientFactory,
        EntitiesTransactionsProvider $entitiesTransactionsProvider,
        PaymentTransactionProvider $paymentTransactionProvider
    ) {
        parent::__construct($clientFactory);
        $this->entitiesTransactionsProvider = $entitiesTransactionsProvider;
        $this->paymentTransactionProvider = $paymentTransactionProvider;
    }

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
                $response->setSuccessful(false);
            }
            $this->paymentTransactionProvider->savePaymentTransaction($transaction);
        }

        return $response;
    }
}
