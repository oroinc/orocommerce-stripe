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
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\Webhook\PaymentIntentCanceledStripeActionExecutor;
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
final class PaymentIntentCanceledStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private PaymentIntentCanceledStripeActionExecutor $executor;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    protected function setUp(): void
    {
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->executor = new PaymentIntentCanceledStripeActionExecutor(
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
            ->setActive(true);
    }

    private function createStripeEvent(): StripeEvent
    {
        return StripeEvent::constructFrom([
            'type' => 'payment_intent.canceled',
            'id' => 'evt_123',
            'data' => [
                'object' => [
                    'object' => 'payment_intent',
                    'id' => 'pi_123',
                    'cancellation_reason' => 'abandoned',
                ],
            ],
        ]);
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue(
            $this->executor->isSupportedByActionName(
                PaymentIntentCanceledStripeActionExecutor::ACTION_NAME
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
        $paymentTransaction->setAction(PaymentMethodInterface::CAPTURE);

        $stripeAction = new StripePaymentIntentWebhookAction(
            StripeEvent::constructFrom(['type' => 'payment_intent.canceled']),
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
            StripeEvent::constructFrom(['type' => 'payment_intent.canceled']),
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

    public function testExecuteActionSuccessfullyWhenCancelTransactionExistsAndInactive(): void
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

        $cancelTransaction = new PaymentTransaction();
        ReflectionUtil::setId($cancelTransaction, 42);
        $cancelTransaction->setAction(PaymentMethodInterface::CANCEL);
        $cancelTransaction->setSuccessful(true);
        $cancelTransaction->setActive(false);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::CANCEL, $authorizeTransaction, ['reference' => $stripePaymentIntent->id])
            ->willReturn($cancelTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($cancelTransaction);

        $this->loggerMock
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Unexpected state while handling the event "{eventType}" '
                . 'on the payment transaction #{authorizePaymentTransactionId}: '
                . 'the existing "cancel" transaction #{cancelPaymentTransactionId} is not active',
                [
                    'eventType' => 'payment_intent.canceled',
                    'authorizePaymentTransactionId' => $authorizeTransaction->getId(),
                    'cancelPaymentTransactionId' => $cancelTransaction->getId(),
                ]
            );

        $expectedTransaction = clone $cancelTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::CANCEL_REASON,
                    $stripePaymentIntent->cancellation_reason
                )
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $cancelTransaction
        );
        self::assertFalse($authorizeTransaction->isActive());
    }

    public function testExecuteActionSuccessfullyWhenCancelTransactionExistsAndActive(): void
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

        $cancelTransaction = new PaymentTransaction();
        ReflectionUtil::setId($cancelTransaction, 42);
        $cancelTransaction->setAction(PaymentMethodInterface::CANCEL);
        $cancelTransaction->setSuccessful(true);
        $cancelTransaction->setActive(true);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::CANCEL, $authorizeTransaction, ['reference' => $stripePaymentIntent->id])
            ->willReturn($cancelTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($cancelTransaction);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $cancelTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::CANCEL_REASON,
                    $stripePaymentIntent->cancellation_reason
                )
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $cancelTransaction
        );
        self::assertFalse($authorizeTransaction->isActive());
    }

    public function testExecuteActionSuccessfullyWhenCancelTransactionNew(): void
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

        $cancelTransaction = new PaymentTransaction();
        $cancelTransaction->setAction(PaymentMethodInterface::CANCEL);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::CANCEL, $authorizeTransaction, ['reference' => $stripePaymentIntent->id])
            ->willReturn($cancelTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($cancelTransaction);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $cancelTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::CANCEL_REASON,
                    $stripePaymentIntent->cancellation_reason
                )
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $cancelTransaction
        );
        self::assertFalse($authorizeTransaction->isActive());
    }
}
