<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

/**
 * Prepare request data for confirm API request.
 */
class ConfirmRequest extends StripeApiRequestAbstract
{
    public const PAYMENT_INTENT_ID_PARAM = 'paymentIntentId';

    #[\Override]
    public function getRequestData(): array
    {
        return [];
    }

    #[\Override]
    public function getPaymentId(): ?string
    {
        $paymentData = $this->getTransactionAdditionalData();

        if (empty($paymentData) || !isset($paymentData[self::PAYMENT_INTENT_ID_PARAM])) {
            throw new \InvalidArgumentException(
                sprintf('Request could not be executed: parameter "%s" is not defined', self::PAYMENT_INTENT_ID_PARAM)
            );
        }

        return $paymentData[self::PAYMENT_INTENT_ID_PARAM];
    }
}
