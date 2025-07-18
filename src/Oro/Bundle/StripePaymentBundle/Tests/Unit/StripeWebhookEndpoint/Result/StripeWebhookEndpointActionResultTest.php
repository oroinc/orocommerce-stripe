<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEndpoint\Result;

use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResult;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\WebhookEndpoint as StripeWebhookEndpoint;

final class StripeWebhookEndpointActionResultTest extends TestCase
{
    public function testConstructorAndGettersWithSuccessfulResultAndWebhookEndpoint(): void
    {
        $webhookEndpoint = new StripeWebhookEndpoint('we_123');
        $result = new StripeWebhookEndpointActionResult(true, $webhookEndpoint);

        self::assertTrue($result->isSuccessful());
        self::assertSame($webhookEndpoint, $result->getStripeObject());
    }

    public function testConstructorAndGettersWithSuccessfulResultAndNullWebhookEndpoint(): void
    {
        $result = new StripeWebhookEndpointActionResult(true, null);

        self::assertTrue($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndWebhookEndpoint(): void
    {
        $webhookEndpoint = new StripeWebhookEndpoint('we_123');
        $result = new StripeWebhookEndpointActionResult(false, $webhookEndpoint);

        self::assertFalse($result->isSuccessful());
        self::assertSame($webhookEndpoint, $result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndNullWebhookEndpoint(): void
    {
        $result = new StripeWebhookEndpointActionResult(false, null);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndStripeError(): void
    {
        $errorMessage = 'Stripe error message';
        $stripeError = StripeInvalidRequestException::factory($errorMessage);
        $result = new StripeWebhookEndpointActionResult(false, null, $stripeError);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
        self::assertSame($stripeError, $result->getStripeError());
    }

    public function testToArrayWithSuccessfulResultAndWebhookEndpoint(): void
    {
        $webhookEndpoint = new StripeWebhookEndpoint('we_123');
        $result = new StripeWebhookEndpointActionResult(true, $webhookEndpoint);
        $expected = ['successful' => true];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithSuccessfulResultAndNullWebhookEndpoint(): void
    {
        $result = new StripeWebhookEndpointActionResult(true, null);
        $expected = ['successful' => true];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithUnsuccessfulResultAndWebhookEndpoint(): void
    {
        $webhookEndpoint = new StripeWebhookEndpoint('we_123');
        $result = new StripeWebhookEndpointActionResult(false, $webhookEndpoint);
        $expected = ['successful' => false];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithUnsuccessfulResultAndNullWebhookEndpoint(): void
    {
        $result = new StripeWebhookEndpointActionResult(false, null);
        $expected = ['successful' => false];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithStripeError(): void
    {
        $errorMessage = 'Stripe error message';
        $stripeError = StripeInvalidRequestException::factory($errorMessage);

        $result = new StripeWebhookEndpointActionResult(false, null, $stripeError);
        $expected = [
            'successful' => false,
            'error' => $errorMessage,
        ];

        self::assertSame($expected, $result->toArray());
    }
}
