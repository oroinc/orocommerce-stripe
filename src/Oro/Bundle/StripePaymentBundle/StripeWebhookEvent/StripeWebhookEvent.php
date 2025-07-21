<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEvent;

use Oro\Bundle\PaymentBundle\Event\AbstractCallbackEvent;
use Stripe\Event as StripeEvent;

/**
 * Dispatched when a Stripe Event webhook arrives.
 */
class StripeWebhookEvent extends AbstractCallbackEvent
{
    public function __construct(private readonly StripeEvent $stripeEvent, array $data = [])
    {
        parent::__construct($data);
    }

    #[\Override]
    public function getEventName(): string
    {
        return 'oro_payment.callback.stripe_webhook';
    }

    public function getStripeEvent(): StripeEvent
    {
        return $this->stripeEvent;
    }
}
