<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeCustomer\Result;

use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResult;
use PHPUnit\Framework\TestCase;
use Stripe\Customer as StripeCustomer;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;

final class StripeCustomerActionResultTest extends TestCase
{
    public function testConstructorAndGettersWithSuccessfulResultAndCustomer(): void
    {
        $stripeCustomer = new StripeCustomer('cus_123');
        $result = new StripeCustomerActionResult(true, $stripeCustomer);

        self::assertTrue($result->isSuccessful());
        self::assertSame($stripeCustomer, $result->getStripeObject());
    }

    public function testConstructorAndGettersWithSuccessfulResultAndNullCustomer(): void
    {
        $result = new StripeCustomerActionResult(true, null);

        self::assertTrue($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndCustomer(): void
    {
        $customer = new StripeCustomer('cus_123');
        $result = new StripeCustomerActionResult(false, $customer);

        self::assertFalse($result->isSuccessful());
        self::assertSame($customer, $result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndNullCustomer(): void
    {
        $result = new StripeCustomerActionResult(false, null);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testConstructorAndGettersWithUnsuccessfulResultAndStripeError(): void
    {
        $errorMessage = 'Stripe error message';
        $stripeError = StripeInvalidRequestException::factory($errorMessage);
        $result = new StripeCustomerActionResult(false, null, $stripeError);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
        self::assertSame($stripeError, $result->getStripeError());
    }

    public function testToArrayWithSuccessfulResultAndCustomer(): void
    {
        $customer = new StripeCustomer('cus_123');
        $result = new StripeCustomerActionResult(true, $customer);
        $expected = ['successful' => true];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithSuccessfulResultAndNullCustomer(): void
    {
        $result = new StripeCustomerActionResult(true, null);
        $expected = ['successful' => true];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithUnsuccessfulResultAndCustomer(): void
    {
        $customer = new StripeCustomer('cus_123');
        $result = new StripeCustomerActionResult(false, $customer);
        $expected = ['successful' => false];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithUnsuccessfulResultAndNullCustomer(): void
    {
        $result = new StripeCustomerActionResult(false, null);
        $expected = ['successful' => false];

        self::assertSame($expected, $result->toArray());
    }

    public function testToArrayWithStripeError(): void
    {
        $errorMessage = 'Stripe error message';
        $stripeError = StripeInvalidRequestException::factory($errorMessage);

        $result = new StripeCustomerActionResult(false, null, $stripeError);
        $expected = [
            'successful' => false,
            'error' => $errorMessage,
        ];

        self::assertSame($expected, $result->toArray());
    }
}
