<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Event;

use Oro\Bundle\StripeBundle\Event\StripeEvent;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\TestCase;

class StripeEventTest extends TestCase
{
    public function testStripeEvent()
    {
        $responseObject = new PaymentIntentResponse();
        $event = new StripeEvent(
            'test_event_name',
            'stripe_payment',
            $responseObject
        );

        $this->assertEquals('test_event_name', $event->getEventName());
        $this->assertEquals('stripe_payment', $event->getPaymentMethodIdentifier());
        $this->assertSame($responseObject, $event->getData());
    }
}
