<?php

namespace Oro\Bundle\StripeBundle\Client\Response;

use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Represents common response for PaymentIntent STRIPE API
 */
class PurchaseResponse implements StripeApiResponseInterface
{
    private const REQUIRES_ACTION_STATUS = 'requires_action';
    private const USE_STRIPE_SDK_ACTION_TYPE = 'use_stripe_sdk';

    private ResponseObjectInterface $responseObject;

    public function __construct(ResponseObjectInterface $responseObject)
    {
        $this->responseObject = $responseObject;
    }

    public function prepareResponse(): array
    {
        $response = ['successful' => $this->isSuccessful()];

        if ($this->responseObject instanceof PaymentIntentResponse) {
            $response = array_merge($response, [
                'requires_action' => $this->isRequiresAdditionalActions(),
                'payment_intent_client_secret' => $this->isRequiresAdditionalActions()
                    ? $this->responseObject->getClientSecret()
                    : null,
            ]);
        }
        return $response;
    }

    public function isSuccessful(): bool
    {
        return !$this->isRequiresAdditionalActions()
            && in_array($this->responseObject->getStatus(), [
                self::SUCCESS_STATUS,
                self::REQUIRES_CAPTURE
            ]);
    }

    private function isRequiresAdditionalActions(): bool
    {
        return $this->responseObject->getStatus() === self::REQUIRES_ACTION_STATUS
            && $this->responseObject->getNextActionType() === self::USE_STRIPE_SDK_ACTION_TYPE;
    }
}
