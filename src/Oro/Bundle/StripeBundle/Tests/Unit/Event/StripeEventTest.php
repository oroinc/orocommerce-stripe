<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Event;

use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripeBundle\Event\StripeEvent;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\TestCase;

class StripeEventTest extends TestCase
{
    public function testStripeEvent()
    {
        $responseObject = new PaymentIntentResponse();
        $paymentConfig = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment'
        ]);
        $event = new StripeEvent(
            'test_event_name',
            $paymentConfig,
            $responseObject
        );

        $this->assertEquals('test_event_name', $event->getEventName());
        $this->assertEquals('stripe_payment', $event->getPaymentMethodIdentifier());
        $this->assertSame($responseObject, $event->getData());
        $this->assertSame($paymentConfig, $event->getPaymentConfig());
    }
}
