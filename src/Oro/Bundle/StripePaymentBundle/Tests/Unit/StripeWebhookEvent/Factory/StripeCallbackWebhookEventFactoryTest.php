<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEvent\Factory;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Factory\StripeCallbackWebhookEventFactory;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider\PaymentTransactionByStripeEventProviderInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEvent;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\SignatureVerificationException as StripeSignatureVerificationException;
use Stripe\Webhook as StripeWebhook;

final class StripeCallbackWebhookEventFactoryTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const string WEBHOOK_ACCESS_ID = 'sample_access_id';
    private const string PAYMENT_METHOD_IDENTIFIER = 'sample_payment_method';
    private const string WEBHOOK_SECRET = 'sample_secret';

    private StripeCallbackWebhookEventFactory $factory;

    private MockObject&PaymentTransactionByStripeEventProviderInterface $paymentTransactionByStripeEventProvider;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    protected function setUp(): void
    {
        $this->paymentTransactionByStripeEventProvider = $this->createMock(
            PaymentTransactionByStripeEventProviderInterface::class
        );

        $this->factory = new StripeCallbackWebhookEventFactory(
            $this->paymentTransactionByStripeEventProvider
        );

        $this->setUpLoggerMock($this->factory);

        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
    }

    public function testCreateWhenWebhookVerificationFails(): void
    {
        $webhookSignature = 'invalid_signature';

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getWebhookAccessId')
            ->willReturn(self::WEBHOOK_ACCESS_ID);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getWebhookSecret')
            ->willReturn(self::WEBHOOK_SECRET);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to create a StripeWebhookEvent from request: {message}',
                [
                    'message' => 'Unable to extract timestamp and signatures from header',
                    'throwable' => StripeSignatureVerificationException::factory(
                        'Unable to extract timestamp and signatures from header',
                        '{}',
                        $webhookSignature
                    ),
                    'webhookAccessId' => self::WEBHOOK_ACCESS_ID,
                    'webhookPayload' => '{}',
                ]
            );
        $result = $this->factory->createStripeCallbackWebhookEvent(
            $this->stripePaymentElementConfig,
            '{}',
            $webhookSignature
        );

        self::assertNull($result);
    }

    public function testCreateWhenPaymentTransactionNotFound(): void
    {
        $webhookPayload = '{
            "id": "evt_test_123",
            "object": "event",
            "api_version": "2025-04-01",
            "created": 1629999999,
            "data": {},
            "livemode": false,
            "pending_webhooks": 1,
            "request": null,
            "type": "payment_intent.succeeded"
        }';
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $webhookPayload;
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);
        $webhookSignature = "t=$timestamp,v1=$signature";

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getWebhookSecret')
            ->willReturn(self::WEBHOOK_SECRET);

        $this->paymentTransactionByStripeEventProvider
            ->expects(self::once())
            ->method('findPaymentTransactionByStripeEvent')
            ->willReturn(null);

        $stripeEvent = StripeWebhook::constructEvent($webhookPayload, $webhookSignature, self::WEBHOOK_SECRET);

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Failed to create a StripeWebhookEvent from request: '
                . 'payment transaction is not found for Stripe Event #{stripeEventId}',
                [
                    'stripeEventId' => $stripeEvent->id,
                    'stripeEvent' => $stripeEvent->toArray(),
                ]
            );

        $result = $this->factory->createStripeCallbackWebhookEvent(
            $this->stripePaymentElementConfig,
            $webhookPayload,
            $webhookSignature
        );

        self::assertNull($result);
    }

    public function testCreateSuccessfully(): void
    {
        $webhookPayload = '{
            "id": "evt_test_123",
            "object": "event",
            "api_version": "2020-08-27",
            "created": 1629999999,
            "data": {
                "object": {
                    "id": "pi_test_123",
                    "object": "payment_intent",
                    "amount": 1000,
                    "currency": "usd",
                    "status": "succeeded"
                }
            },
            "livemode": false,
            "pending_webhooks": 1,
            "request": null,
            "type": "payment_intent.succeeded"
        }';

        $timestamp = time();
        $signedPayload = $timestamp . '.' . $webhookPayload;
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);
        $webhookSignature = "t=$timestamp,v1=$signature";

        $paymentTransaction = new PaymentTransaction();

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getWebhookSecret')
            ->willReturn(self::WEBHOOK_SECRET);

        $this->paymentTransactionByStripeEventProvider
            ->expects(self::once())
            ->method('findPaymentTransactionByStripeEvent')
            ->willReturn($paymentTransaction);

        $this->assertLoggerNotCalled();

        $result = $this->factory->createStripeCallbackWebhookEvent(
            $this->stripePaymentElementConfig,
            $webhookPayload,
            $webhookSignature
        );

        $expected = new StripeWebhookEvent(
            StripeWebhook::constructEvent(
                $webhookPayload,
                $webhookSignature,
                self::WEBHOOK_SECRET
            )
        );
        $expected->setPaymentTransaction($paymentTransaction);
        // Avoids date mismatch errors during comparison.
        $expected->getResponse()->headers = $result->getResponse()->headers;

        self::assertEquals($expected, $result);
    }

    public function testCreateWithCustomTolerance(): void
    {
        $webhookPayload = '{
            "id": "evt_test_123",
            "object": "event",
            "api_version": "2020-08-27",
            "created": 1629999999,
            "data": {
                "object": {
                    "id": "pi_test_123",
                    "object": "payment_intent",
                    "amount": 1000,
                    "currency": "usd",
                    "status": "succeeded"
                }
            },
            "livemode": false,
            "pending_webhooks": 1,
            "request": null,
            "type": "payment_intent.succeeded"
        }';

        $timestamp = time() - 1000; // Old timestamp to test tolerance
        $signedPayload = $timestamp . '.' . $webhookPayload;
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);
        $webhookSignature = "t=$timestamp,v1=$signature";

        $paymentTransaction = new PaymentTransaction();
        $customTolerance = 2000; // Large enough to accept the old timestamp

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getWebhookSecret')
            ->willReturn(self::WEBHOOK_SECRET);

        $this->paymentTransactionByStripeEventProvider
            ->expects(self::once())
            ->method('findPaymentTransactionByStripeEvent')
            ->willReturn($paymentTransaction);

        $this->assertLoggerNotCalled();

        $result = $this->factory->createStripeCallbackWebhookEvent(
            $this->stripePaymentElementConfig,
            $webhookPayload,
            $webhookSignature,
            $customTolerance
        );

        $expected = new StripeWebhookEvent(
            StripeWebhook::constructEvent(
                $webhookPayload,
                $webhookSignature,
                self::WEBHOOK_SECRET,
                $customTolerance
            )
        );
        $expected->setPaymentTransaction($paymentTransaction);
        // Avoids date mismatch errors during comparison.
        $expected->getResponse()->headers = $result->getResponse()->headers;

        self::assertEquals($expected, $result);
    }
}
