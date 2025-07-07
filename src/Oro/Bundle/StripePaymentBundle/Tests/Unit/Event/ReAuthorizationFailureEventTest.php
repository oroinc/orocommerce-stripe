<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Event;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\Event\ReAuthorizationFailureEvent;
use PHPUnit\Framework\TestCase;

final class ReAuthorizationFailureEventTest extends TestCase
{
    public function testEventConstructionAndGetters(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentMethodResult = [
            'success' => false,
            'error' => 'Insufficient funds',
        ];

        $event = new ReAuthorizationFailureEvent($paymentTransaction, $paymentMethodResult);

        self::assertSame($paymentTransaction, $event->getPaymentTransaction());
        self::assertSame($paymentMethodResult, $event->getPaymentMethodResult());
    }

    public function testEmptyPaymentMethodResult(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $emptyResult = [];

        $event = new ReAuthorizationFailureEvent($paymentTransaction, $emptyResult);

        self::assertSame([], $event->getPaymentMethodResult());
        self::assertEmpty($event->getPaymentMethodResult());
    }
}
