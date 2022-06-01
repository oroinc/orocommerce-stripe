<?php

namespace Oro\Bundle\StripeBundle\EventHandler;

use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\EventHandler\Exception\NotSupportedEventException;

/**
 * Register all StripeEventHandlerInterface services and find service which is able to handle event.
 */
class StripeEventHandlerRegistry
{
    private iterable $handlers;

    /**
     * @param iterable|StripeEventHandlerInterface[] $handlers
     */
    public function __construct(iterable $handlers)
    {
        $this->handlers = $handlers;
    }

    public function getHandler(StripeEventInterface $event): StripeEventHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->isSupported($event)) {
                return $handler;
            }
        }

        throw new NotSupportedEventException(sprintf('Event "%s" is not supported', $event->getEventName()));
    }
}
