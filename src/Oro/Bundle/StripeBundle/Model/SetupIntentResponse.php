<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Stores data for setup intent object responses.
 */
class SetupIntentResponse extends AbstractResponseObject implements ResponseObjectInterface
{
    public const SETUP_INTENT_ID_PARAM = 'setupIntentId';
    private const STATUS_FIELD_NAME = 'status';
    private const ID_FIELD_NAME = 'id';
    private const CLIENT_SECRET_FIELD_NAME = 'client_secret';
    private const NEXT_ACTION_FIELD_NAME = 'next_action';

    #[\Override]
    public function getStatus(): string
    {
        return $this->getValue(self::STATUS_FIELD_NAME);
    }

    #[\Override]
    public function getIdentifier(): string
    {
        return $this->getValue(self::ID_FIELD_NAME);
    }

    #[\Override]
    public function getData(): array
    {
        return [
            self::SETUP_INTENT_ID_PARAM => $this->getValue(self::ID_FIELD_NAME),
            'data' => [
                'cancellation_reason' => $this->getValue('cancellation_reason'),
                'created' => $this->getValue('created'),
                'customer' => $this->getValue('customer'),
                'description' => $this->getValue('description'),
                'metadata' => $this->getValue('metadata'),
                'payment_method' => $this->getValue('payment_method'),
                'usage' => $this->getValue('usage'),
                'status' => $this->getStatus(),
                'return_url' => $this->getValue('return_url'),
                'livemode' => $this->getValue('livemode'),
                'flow_directions' => $this->getValue('flow_directions'),
                'last_setup_error' => $this->getValue('last_setup_error'),
                'latest_attempt' => $this->getValue('latest_attempt'),
                'mandate' => $this->getValue('mandate'),
                'on_behalf_of' => $this->getValue('on_behalf_of'),
                'single_use_mandate' => $this->getValue('single_use_mandate')
            ]
        ];
    }

    public function getNextActionType(): ?string
    {
        $nextAction = $this->getValue(self::NEXT_ACTION_FIELD_NAME);
        if (null !== $nextAction && isset($nextAction['type'])) {
            return $nextAction['type'];
        }

        return null;
    }

    public function getClientSecret(): ?string
    {
        return $this->getValue(self::CLIENT_SECRET_FIELD_NAME);
    }
}
