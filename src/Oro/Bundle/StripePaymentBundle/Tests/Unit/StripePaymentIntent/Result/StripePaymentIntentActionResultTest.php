<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Result;

use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\CardException as StripeCardException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\PaymentIntent as StripePaymentIntent;
use Stripe\StripeObject;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class StripePaymentIntentActionResultTest extends TestCase
{
    public function testConstructorAndGettersWithSuccessfulResultAndPaymentIntent(): void
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $result = new StripePaymentIntentActionResult(true, $paymentIntent);

        self::assertTrue($result->isSuccessful());
        self::assertSame($paymentIntent, $result->getStripeObject());
    }

    public function testConstructorAndGettersWithSuccessfulResultAndNullPaymentIntent(): void
    {
        $result = new StripePaymentIntentActionResult(true, null);

        self::assertTrue($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndPaymentIntent(): void
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $result = new StripePaymentIntentActionResult(false, $paymentIntent);

        self::assertFalse($result->isSuccessful());
        self::assertSame($paymentIntent, $result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndNullPaymentIntent(): void
    {
        $result = new StripePaymentIntentActionResult(false, null);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndStripeError(): void
    {
        $errorMessage = 'Stripe error message';
        $stripeError = StripeInvalidRequestException::factory($errorMessage);
        $result = new StripePaymentIntentActionResult(false, null, $stripeError);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
        self::assertSame($stripeError, $result->getStripeError());
    }

    public function testToArrayWithNullPaymentIntent(): void
    {
        $result = new StripePaymentIntentActionResult(true, null);
        $expected = ['successful' => true];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithPaymentIntentWithoutStatusOrSecret(): void
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = null;
        $paymentIntent->client_secret = null;

        $result = new StripePaymentIntentActionResult(true, $paymentIntent);
        $expected = ['successful' => true];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithPaymentIntentWithRequiresActionStatus(): void
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'requires_action';
        $paymentIntent->client_secret = null;

        $result = new StripePaymentIntentActionResult(true, $paymentIntent);
        $expected = [
            'successful' => true,
            'requiresAction' => true,
        ];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithPaymentIntentWithClientSecret(): void
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = null;
        $paymentIntent->client_secret = 'test_secret_123';

        $result = new StripePaymentIntentActionResult(true, $paymentIntent);
        $expected = [
            'successful' => true,
            'paymentIntentClientSecret' => 'test_secret_123',
        ];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithPaymentIntentWithBothRequiresActionAndClientSecret(): void
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'requires_action';
        $paymentIntent->client_secret = 'test_secret_123';

        $result = new StripePaymentIntentActionResult(true, $paymentIntent);
        $expected = [
            'successful' => true,
            'requiresAction' => true,
            'paymentIntentClientSecret' => 'test_secret_123',
        ];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithUnsuccessfulResultAndPaymentIntent(): void
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'canceled';

        $result = new StripePaymentIntentActionResult(false, $paymentIntent);
        $expected = [
            'successful' => false,
        ];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithStripeError(): void
    {
        $errorMessage = 'Stripe error message';
        $stripeError = StripeInvalidRequestException::factory($errorMessage);

        $result = new StripePaymentIntentActionResult(false, null, $stripeError);
        $expected = [
            'successful' => false,
            'error' => $errorMessage,
            'errorCode' => 0,
            'stripeCode' => null,
        ];

        self::assertSame($expected, $result->toArray());
    }

    public function testGetStripeErrorReturnsNullWhenNoError(): void
    {
        $result = new StripePaymentIntentActionResult(true, null);

        self::assertNull($result->getStripeError());
    }

    public function testToArrayWithPaymentIntentWithLastPaymentError(): void
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->last_payment_error = new StripeObject();
        $paymentIntent->last_payment_error['message'] = 'Payment declined';
        $paymentIntent->last_payment_error['code'] = 'card_declined';
        $paymentIntent->last_payment_error['decline_code'] = 'insufficient_funds';

        $result = new StripePaymentIntentActionResult(false, $paymentIntent);
        $expected = [
            'successful' => false,
            'error' => 'Payment declined',
            'errorCode' => 'card_declined',
            'declineCode' => 'insufficient_funds',
        ];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithPaymentIntentWithLastPaymentErrorPartial(): void
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->last_payment_error = new StripeObject();
        $paymentIntent->last_payment_error['message'] = 'Payment failed';

        $result = new StripePaymentIntentActionResult(false, $paymentIntent);
        $expected = [
            'successful' => false,
            'error' => 'Payment failed',
            'errorCode' => null,
            'declineCode' => null,
        ];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithApiErrorException(): void
    {
        $errorMessage = 'API error occurred';
        $stripeCode = 'invalid_request_error';
        $stripeError = StripeInvalidRequestException::factory(
            $errorMessage,
            400,
            null,
            null,
            null,
            $stripeCode
        );

        $result = new StripePaymentIntentActionResult(false, null, $stripeError);
        $expected = [
            'successful' => false,
            'error' => $errorMessage,
            'errorCode' => 0,
            'stripeCode' => $stripeCode,
        ];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithCardException(): void
    {
        $errorMessage = 'Card was declined';
        $stripeCode = 'card_declined';
        $declineCode = 'generic_decline';
        $stripeError = StripeCardException::factory(
            $errorMessage,
            402,
            null,
            null,
            null,
            $stripeCode,
            $declineCode
        );

        $result = new StripePaymentIntentActionResult(false, null, $stripeError);
        $expected = [
            'successful' => false,
            'error' => $errorMessage,
            'errorCode' => 0,
            'stripeCode' => $stripeCode,
            'declineCode' => $declineCode,
        ];

        self::assertSame($expected, $result->toArray());
    }
}
