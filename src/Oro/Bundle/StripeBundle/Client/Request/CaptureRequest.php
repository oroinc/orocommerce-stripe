<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\StripeBundle\Converter\PaymentAmountConverter;

/**
 * Prepare request data to perform capture request to Stripe API.
 */
class CaptureRequest extends StripeApiRequestAbstract
{
    public function getRequestData(): array
    {
        return [
            'amount_to_capture' => PaymentAmountConverter::convertToStripeFormat((float)$this->transaction->getAmount())
        ];
    }
}
