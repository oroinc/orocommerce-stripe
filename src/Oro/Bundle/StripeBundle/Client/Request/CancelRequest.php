<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;

/**
 * Prepare data for cancel request.
 */
class CancelRequest extends StripeApiRequestAbstract
{
    private const DEFAULT_CANCELLATION_REASON_PARAM = 'requested_by_customer';
    private const CANCEL_REASON_PARAM = 'cancelReason';

    #[\Override]
    public function getPaymentId(): ?string
    {
        $paymentId = $this->transaction->getSourcePaymentTransaction()
            ?->getResponse()[PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM] ?? null;

        if (null === $paymentId) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Request could not be executed: parameter "%s" is not defined',
                    PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM
                )
            );
        }

        return $paymentId;
    }

    #[\Override]
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
