<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;

/**
 * Handle payment confirmation and perform payments for each entity.
 */
class MultipleConfirmPaymentAction extends PurchasePaymentActionAbstract implements PaymentActionInterface
{
    #[\Override]
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $client = $this->getClient($config);
        $additionalOptions = $this->getTransactionAdditionalData($paymentTransaction);

        $setupIntentId = $additionalOptions[SetupIntentResponse::SETUP_INTENT_ID_PARAM] ?? null;

        if (!$setupIntentId) {
            throw new \RuntimeException('Unsupported payment transaction');
        }

        $setupIntentResponse = $client->findSetupIntent($setupIntentId);
        $paymentTransaction->setSuccessful(true);

        $response = new StripeApiResponse($setupIntentResponse);

        if ($setupIntentResponse->getStatus() === StripeApiResponseInterface::SUCCESS_STATUS) {
            $response = $this->purchaseMultipleTransactions($config, $paymentTransaction);
        }

        return $response;
    }

    #[\Override]
    public function isApplicable(string $action, PaymentTransaction $paymentTransaction): bool
    {
        return $action === self::CONFIRM_ACTION
            && $this->entitiesTransactionsProvider->hasEntities($paymentTransaction);
    }
}
