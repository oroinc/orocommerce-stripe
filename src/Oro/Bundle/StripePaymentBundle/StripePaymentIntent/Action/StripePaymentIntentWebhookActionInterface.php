<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action;

use Stripe\Event as StripeEvent;

/**
 * Interface for the Stripe PaymentIntents API action model aware of {@see StripeEvent}.
 */
interface StripePaymentIntentWebhookActionInterface extends StripePaymentIntentActionInterface
{
    public function getStripeEvent(): StripeEvent;
}
