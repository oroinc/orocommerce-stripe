<?php

namespace Oro\Bundle\StripeBundle\Client\Response;

use Oro\Bundle\StripeBundle\Model\SetupIntentResponse as SetupIntentResponseObject;

/**
 * Represents common response for SetupIntent STRIPE API
 */
class SetupIntentResponse extends AbstractIntentResponse
{
    #[\Override]
    protected function isResponseObjectSupported(): bool
    {
        return $this->responseObject instanceof SetupIntentResponseObject;
    }

    #[\Override]
    protected function getIntentSecretName(): string
    {
        return 'setup_intent_client_secret';
    }
}
