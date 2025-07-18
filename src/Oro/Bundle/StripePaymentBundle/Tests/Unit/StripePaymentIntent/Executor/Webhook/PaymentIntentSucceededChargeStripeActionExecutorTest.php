<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor\Webhook;

// @codingStandardsIgnoreStart

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\GenericStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\Webhook\PaymentIntentSucceededChargeStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;
use Stripe\PaymentIntent as StripePaymentIntent;

// @codingStandardsIgnoreEnd

final class PaymentIntentSucceededChargeStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private PaymentIntentSucceededChargeStripeActionExecutor $executor;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    protected function setUp(): void
    {
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->executor = new PaymentIntentSucceededChargeStripeActionExecutor(
            new GenericStripeAmountConverter()
        );
        $this->setUpLoggerMock($this->executor);

        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
    }

    private function createChargeTransaction(): PaymentTransaction
    {
        return (new PaymentTransaction())
            ->setAction(PaymentMethodInterface::CHARGE)
            ->setSuccessful(true)
            ->setActive(false);
    }

    private function createStripeEvent(): StripeEvent
    {
        return StripeEvent::constructFrom([
            'type' => 'payment_intent.succeeded',
            'id' => 'evt_123',
            'data' => [
                'object' => [
                    'object' => 'payment_intent',
                    'id' => 'pi_123',
                    'amount_received' => 12345,
                    'currency' => 'usd',
                ],
            ],
        ]);
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue(
            $this->executor->isSupportedByActionName(
                PaymentIntentSucceededChargeStripeActionExecutor::ACTION_NAME
            )
        );
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotSupportedAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::AUTHORIZE));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedClass(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            new PaymentTransaction()
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedEventType(): void
    {
        $stripeAction = new StripePaymentIntentWebhookAction(
            StripeEvent::constructFrom(['type' => 'payment_intent.payment_failed']),
            $this->stripePaymentElementConfig,
            new PaymentTransaction()
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedPaymentTransactionAction(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setAction(PaymentMethodInterface::AUTHORIZE);

        $stripeAction = new StripePaymentIntentWebhookAction(
            StripeEvent::constructFrom(['type' => 'payment_intent.succeeded']),
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrue(): void
    {
        $chargeTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            StripeEvent::constructFrom(['type' => 'payment_intent.succeeded']),
            $this->stripePaymentElementConfig,
            $chargeTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionWhenNotSupportedClass(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            new PaymentTransaction()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Argument $stripeAction is expected to be an instance of %s, got %s',
                StripePaymentIntentWebhookActionInterface::class,
                get_debug_type($stripeAction)
            )
        );

        $this->executor->executeAction($stripeAction);
    }

    public function testExecuteActionSuccessfully(): void
    {
        $stripeEvent = $this->createStripeEvent();

        /** @var StripePaymentIntent $stripePaymentIntent */
        $stripePaymentIntent = $stripeEvent->data->object;

        $chargeTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            $stripeEvent,
            $this->stripePaymentElementConfig,
            $chargeTransaction
        );

        $expectedTransaction = clone $chargeTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful(true)
                ->setActive(false)
                ->setAmount(123.45)
                ->setCurrency('USD')
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $chargeTransaction
        );
    }
}
