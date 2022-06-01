<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Stub;

use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\EventHandler\StripeEventHandlerInterface;

class TestEventHandler implements StripeEventHandlerInterface
{
    public function handle(StripeEventInterface $event): void
    {
    }

    public function isSupported(StripeEventInterface $event): bool
    {
        return $event->getEventName() === 'test_payment.succeeded';
    }
}
