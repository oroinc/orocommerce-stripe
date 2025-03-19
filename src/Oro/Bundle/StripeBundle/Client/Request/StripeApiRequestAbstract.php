<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;

/**
 * Basic functionality to provide Stripe API Requests data.
 */
abstract class StripeApiRequestAbstract implements StripeApiRequestInterface
{
    public const string STRIPE_PAYMENT_METHOD_ID_PARAM = 'stripePaymentMethodId';

    public function __construct(
        protected PaymentTransaction $transaction
    ) {
    }

    #[\Override]
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

    public function getPaymentMethodId(): ?string
    {
        $additionalData = $this->getTransactionAdditionalData();

        if (empty($additionalData) || !isset($additionalData[self::STRIPE_PAYMENT_METHOD_ID_PARAM])) {
            throw new \LogicException(sprintf(
                'Payment request could not be perform: parameter "%s" is empty',
                'stripePaymentMethodId'
            ));
        }

        return $additionalData[self::STRIPE_PAYMENT_METHOD_ID_PARAM];
    }

    public function getTransaction(): PaymentTransaction
    {
        return $this->transaction;
    }

    /**
     * Extract payment options stored in payment transaction additionalData.
     */
    protected function getTransactionAdditionalData(): array
    {
        $additionalData = $this->getTransactionOption($this->transaction, 'additionalData');
        return $additionalData ? json_decode($additionalData, true) : [];
    }

    /**
     * @param PaymentTransaction $paymentTransaction
     * @param string $optionName
     * @return mixed|null
     */
    protected function getTransactionOption(PaymentTransaction $paymentTransaction, string $optionName)
    {
        return $paymentTransaction->getTransactionOptions()[$optionName] ?? null;
    }
}
