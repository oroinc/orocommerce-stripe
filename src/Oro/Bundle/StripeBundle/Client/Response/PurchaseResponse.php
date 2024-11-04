<?php

namespace Oro\Bundle\StripeBundle\Client\Response;

use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;

/**
 * Represents common response for PaymentIntent STRIPE API
 */
class PurchaseResponse extends AbstractIntentResponse
{
    #[\Override]
    protected function isResponseObjectSupported(): bool
    {
        return $this->responseObject instanceof PaymentIntentResponse;
    }

    #[\Override]
    protected function getIntentSecretName(): string
    {
        return 'payment_intent_client_secret';
    }
}
