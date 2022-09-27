<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;

class CancelRequest extends StripeApiRequestAbstract implements StripeApiRequestInterface
{
    private const DEFAULT_CANCELLATION_REASON_PARAM = 'requested_by_customer';
    private const CANCEL_REASON_PARAM = 'cancelReason';

    public function getPaymentId(): ?string
    {
        $paymentResponse = $this->transaction->getSourcePaymentTransaction()->getResponse();

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
     * {@inheritdoc}
     */
    public function getRequestData(): array
    {
        return [
            'cancellation_reason' => $this->getCancellationReason()
        ];
    }

    private function getCancellationReason(): string
    {
        return $this->getTransactionOption($this->getTransaction(), self::CANCEL_REASON_PARAM)
            ?? self::DEFAULT_CANCELLATION_REASON_PARAM;
    }
}
