<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor\Webhook;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\Webhook\PaymentIntentFailedCaptureStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;
use Stripe\PaymentIntent as StripePaymentIntent;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class PaymentIntentFailedCaptureStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private PaymentIntentFailedCaptureStripeActionExecutor $executor;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    protected function setUp(): void
    {
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->executor = new PaymentIntentFailedCaptureStripeActionExecutor(
            $this->paymentTransactionProvider
        );
        $this->setUpLoggerMock($this->executor);

        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
    }

    private function createAuthorizeTransaction(): PaymentTransaction
    {
        return (new PaymentTransaction())
            ->setAction(PaymentMethodInterface::AUTHORIZE)
            ->setSuccessful(true)
            ->setActive(true)
            ->setAmount(123.45)
            ->setCurrency('USD');
    }

    private function createStripeEvent(): StripeEvent
    {
        return StripeEvent::constructFrom([
            'type' => 'payment_intent.payment_failed',
            'id' => 'evt_123',
            'data' => [
                'object' => [
                    'object' => 'payment_intent',
                    'id' => 'pi_123',
                ],
            ],
        ]);
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue(
            $this->executor->isSupportedByActionName(
                PaymentIntentFailedCaptureStripeActionExecutor::ACTION_NAME
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
            StripeEvent::constructFrom(['type' => 'payment_intent.succeeded']),
            $this->stripePaymentElementConfig,
            new PaymentTransaction()
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedPaymentTransactionAction(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setAction(PaymentMethodInterface::CHARGE);

        $stripeAction = new StripePaymentIntentWebhookAction(
            StripeEvent::constructFrom(['type' => 'payment_intent.payment_failed']),
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrue(): void
    {
        $authorizeTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            StripeEvent::constructFrom(['type' => 'payment_intent.payment_failed']),
            $this->stripePaymentElementConfig,
            $authorizeTransaction
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

    public function testExecuteActionSuccessfullyWhenCaptureTransactionExists(): void
    {
        $stripeEvent = $this->createStripeEvent();

        /** @var StripePaymentIntent $stripePaymentIntent */
        $stripePaymentIntent = $stripeEvent->data->object;

        $authorizeTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            $stripeEvent,
            $this->stripePaymentElementConfig,
            $authorizeTransaction
        );

        $captureTransaction = new PaymentTransaction();
        ReflectionUtil::setId($captureTransaction, 42);
        $captureTransaction->setAction(PaymentMethodInterface::CAPTURE);
        $captureTransaction->setSuccessful(true);
        $captureTransaction->setActive(false);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::CAPTURE, $authorizeTransaction, ['reference' => $stripePaymentIntent->id])
            ->willReturn($captureTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($captureTransaction);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $captureTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful(false)
                ->setActive(false)
                ->setAmount($authorizeTransaction->getAmount())
                ->setCurrency($authorizeTransaction->getCurrency())
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $captureTransaction
        );
        self::assertFalse($authorizeTransaction->isActive());
    }

    public function testExecuteActionSuccessfullyWhenCaptureTransactionNew(): void
    {
        $stripeEvent = $this->createStripeEvent();

        /** @var StripePaymentIntent $stripePaymentIntent */
        $stripePaymentIntent = $stripeEvent->data->object;

        $authorizeTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            $stripeEvent,
            $this->stripePaymentElementConfig,
            $authorizeTransaction
        );

        $captureTransaction = new PaymentTransaction();
        $captureTransaction->setAction(PaymentMethodInterface::CAPTURE);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::CAPTURE, $authorizeTransaction, ['reference' => $stripePaymentIntent->id])
            ->willReturn($captureTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($captureTransaction);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $captureTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful(false)
                ->setActive(false)
                ->setAmount($authorizeTransaction->getAmount())
                ->setCurrency($authorizeTransaction->getCurrency())
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $captureTransaction
        );
        self::assertFalse($authorizeTransaction->isActive());
    }
}
