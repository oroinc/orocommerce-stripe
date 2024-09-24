<?php

namespace Oro\Bundle\StripeBundle\Client\Response;

use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Represents common response for Intent STRIPE API
 */
abstract class AbstractIntentResponse implements StripeApiResponseInterface
{
    protected const REQUIRES_ACTION_STATUS = 'requires_action';
    protected const USE_STRIPE_SDK_ACTION_TYPE = 'use_stripe_sdk';

    protected ResponseObjectInterface $responseObject;

    public function __construct(ResponseObjectInterface $responseObject)
    {
        $this->responseObject = $responseObject;
    }

    abstract protected function isResponseObjectSupported(): bool;

    abstract protected function getIntentSecretName(): string;

    #[\Override]
    public function prepareResponse(): array
    {
        $response = [
            'successful' => $this->isSuccessful()
        ];

        if ($this->isResponseObjectSupported()) {
            $response['requires_action'] = $this->isRequiresAdditionalActions();
            $response[$this->getIntentSecretName()] = $this->isRequiresAdditionalActions()
                ? $this->responseObject->getClientSecret()
                : null;
        }

        return $response;
    }

    #[\Override]
    public function isSuccessful(): bool
    {
        return !$this->isRequiresAdditionalActions()
            && in_array($this->responseObject->getStatus(), [
                self::SUCCESS_STATUS,
                self::REQUIRES_CAPTURE
            ], true);
    }

    protected function isRequiresAdditionalActions(): bool
    {
        return $this->responseObject->getStatus() === self::REQUIRES_ACTION_STATUS
            && $this->responseObject->getNextActionType() === self::USE_STRIPE_SDK_ACTION_TYPE;
    }
}
