<?php

namespace Oro\Bundle\StripeBundle\EventHandler;

use Oro\Bundle\StripeBundle\Event\StripeEventFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Basic entrypoint to handle incoming event data.
 */
class StripeWebhookEventHandler
{
    private StripeEventFactoryInterface $eventFactory;
    private StripeEventHandlerRegistry $handlerRegistry;

    public function __construct(StripeEventFactoryInterface $eventFactory, StripeEventHandlerRegistry $handlerRegistry)
    {
        $this->eventFactory = $eventFactory;
        $this->handlerRegistry = $handlerRegistry;
    }

    public function handleEvent(Request $request): void
    {
        $event = $this->eventFactory->createEventFromRequest($request);
        $this->handlerRegistry
            ->getHandler($event)
            ->handle($event);
    }
}
