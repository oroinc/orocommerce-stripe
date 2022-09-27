<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;

/**
 * Prepare data for refund request.
 */
class RefundRequest extends StripeApiRequestAbstract
{
    private const DEFAULT_REFUND_REASON_PARAM = 'requested_by_customer';
    private const REFUND_REASON_PARAM = 'refundReason';

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

    public function getRequestData(): array
    {
        return [
            'payment_intent' => $this->getPaymentId(),
            'reason' => $this->getRefundReason(),
        ];
    }

    private function getRefundReason(): string
    {
        return $this->getTransactionOption($this->getTransaction(), self::REFUND_REASON_PARAM)
            ?? self::DEFAULT_REFUND_REASON_PARAM;
    }
}
