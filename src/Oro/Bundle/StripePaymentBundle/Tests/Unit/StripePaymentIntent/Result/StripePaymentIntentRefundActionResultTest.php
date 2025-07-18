<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Result;

use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentRefundActionResult;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\Refund as StripeRefund;

final class StripePaymentIntentRefundActionResultTest extends TestCase
{
    public function testConstructorAndGettersWithSuccessfulResultAndRefund(): void
    {
        $refund = new StripeRefund('re_123');
        $result = new StripePaymentIntentRefundActionResult(true, $refund);

        self::assertTrue($result->isSuccessful());
        self::assertSame($refund, $result->getStripeObject());
    }

    public function testConstructorAndGettersWithSuccessfulResultAndNullRefund(): void
    {
        $result = new StripePaymentIntentRefundActionResult(true, null);

        self::assertTrue($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndRefund(): void
    {
        $refund = new StripeRefund('re_123');
        $result = new StripePaymentIntentRefundActionResult(false, $refund);

        self::assertFalse($result->isSuccessful());
        self::assertSame($refund, $result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndNullRefund(): void
    {
        $result = new StripePaymentIntentRefundActionResult(false, null);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndStripeError(): void
    {
        $errorMessage = 'Stripe error message';
        $stripeError = StripeInvalidRequestException::factory($errorMessage);
        $result = new StripePaymentIntentRefundActionResult(false, null, $stripeError);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
        self::assertSame($stripeError, $result->getStripeError());
    }

    public function testToArrayWithSuccessfulResultAndRefund(): void
    {
        $refund = new StripeRefund('re_123');
        $result = new StripePaymentIntentRefundActionResult(true, $refund);
        $expected = ['successful' => true];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithSuccessfulResultAndNullRefund(): void
    {
        $result = new StripePaymentIntentRefundActionResult(true, null);
        $expected = ['successful' => true];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithUnsuccessfulResultAndRefund(): void
    {
        $refund = new StripeRefund('re_123');
        $result = new StripePaymentIntentRefundActionResult(false, $refund);
        $expected = ['successful' => false];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithUnsuccessfulResultAndNullRefund(): void
    {
        $result = new StripePaymentIntentRefundActionResult(false, null);
        $expected = ['successful' => false];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithStripeError(): void
    {
        $errorMessage = 'Stripe error message';
        $stripeError = StripeInvalidRequestException::factory($errorMessage);

        $result = new StripePaymentIntentRefundActionResult(false, null, $stripeError);
        $expected = [
            'successful' => false,
            'error' => $errorMessage,
        ];

        self::assertSame($expected, $result->toArray());
    }
}
