<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;

/**
 * Prepare data for authorization request.
 */
class AuthorizeRequest extends PurchaseRequest
{
    #[\Override]
    public function getRequestData(): array
    {
        $requestData = parent::getRequestData();
        // Authorization request should always have MANUAL capture method.
        $requestData['capture_method'] = StripePaymentActionMapper::MANUAL;

        return $requestData;
    }
}
