<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Event;

use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripeBundle\Event\StripeEvent;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\TestCase;

class StripeEventTest extends TestCase
{
    public function testStripeEvent(): void
    {
        $responseObject = new PaymentIntentResponse();
        $paymentConfig = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_1',
        ]);
        $event = new StripeEvent(
            'test_event_name',
            $paymentConfig,
            $responseObject,
            'stripe_payment_11'
        );

        self::assertEquals('test_event_name', $event->getEventName());
        self::assertEquals('stripe_payment_11', $event->getPaymentMethodIdentifier());
        self::assertSame($responseObject, $event->getData());
        self::assertSame($paymentConfig, $event->getPaymentConfig());
    }

    public function testStripeEventWithoutPaymentMethodIdentifier(): void
    {
        $responseObject = new PaymentIntentResponse();
        $paymentConfig = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_1',
        ]);

        $event = new StripeEvent('test_event_name', $paymentConfig, $responseObject);

        self::assertEquals('test_event_name', $event->getEventName());
        self::assertEquals($paymentConfig->getPaymentMethodIdentifier(), $event->getPaymentMethodIdentifier());
        self::assertSame($responseObject, $event->getData());
        self::assertSame($paymentConfig, $event->getPaymentConfig());
    }
}
