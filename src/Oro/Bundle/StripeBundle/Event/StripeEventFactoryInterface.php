<?php

namespace Oro\Bundle\StripeBundle\Event;

use Symfony\Component\HttpFoundation\Request;

/**
 * Basic interface for Stripe event creation logic.
 */
interface StripeEventFactoryInterface
{
    /**
     * Create request object from request data.
     *
     * @param Request $request
     * @return StripeEventInterface
     */
    public function createEventFromRequest(Request $request): StripeEventInterface;
}
