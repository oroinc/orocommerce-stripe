<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;

/**
 * Basic functionality to provide Stripe API Requests data.
 */
abstract class StripeApiRequestAbstract implements StripeApiRequestInterface
{
    protected PaymentTransaction $transaction;

    public function __construct(PaymentTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function getPaymentId(): ?string
    {
        $paymentResponse = $this->transaction->getResponse();

        if (!$paymentResponse || !$paymentResponse[PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM]) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Request could not be executed: parameter "%s" is not defined',
                    PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM
                )
            );
        }

        return $paymentResponse[PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM];
    }

    /**
     * Extract payment options stored in payment transaction additionalData.
     */
    protected function getTransactionAdditionalData(): array
    {
        $transactionOptions = $this->transaction->getTransactionOptions();
        return isset($transactionOptions['additionalData'])
            ? json_decode($transactionOptions['additionalData'], true)
            : [];
    }
}
