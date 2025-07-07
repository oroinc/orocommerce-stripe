<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor\Webhook;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\GenericStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\Webhook\RefundUpdatedStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentRefundActionResult;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;
use Stripe\Refund as StripeRefund;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class RefundUpdatedStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private RefundUpdatedStripeActionExecutor $executor;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    protected function setUp(): void
    {
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->executor = new RefundUpdatedStripeActionExecutor(
            $this->paymentTransactionProvider,
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
            ->setActive(true)
            ->setAmount(123.45)
            ->setCurrency('USD');
    }

    private function createStripeEvent(string $refundStatus): StripeEvent
    {
        return StripeEvent::constructFrom([
            'type' => 'refund.updated',
            'id' => 'evt_123',
            'data' => [
                'object' => [
                    'object' => 'refund',
                    'id' => 're_123',
                    'status' => $refundStatus,
                    'amount' => 12345,
                    'currency' => 'usd',
                    'reason' => 'requested_by_customer',
                    'payment_intent' => 'pi_123',
                ],
            ],
        ]);
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue(
            $this->executor->isSupportedByActionName(
                RefundUpdatedStripeActionExecutor::ACTION_NAME
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
        $paymentTransaction->setAction(PaymentMethodInterface::AUTHORIZE);

        $stripeAction = new StripePaymentIntentWebhookAction(
            StripeEvent::constructFrom(['type' => 'refund.updated']),
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    /**
     * @dataProvider paymentTransactionSupportedActionsProvider
     */
    public function testIsApplicableForActionReturnsTrue(string $action): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setAction($action);
        $paymentTransaction->setSuccessful(true);

        $stripeAction = new StripePaymentIntentWebhookAction(
            StripeEvent::constructFrom(['type' => 'refund.updated']),
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function paymentTransactionSupportedActionsProvider(): array
    {
        return [
            [PaymentMethodInterface::PURCHASE],
            [PaymentMethodInterface::CHARGE],
            [PaymentMethodInterface::CAPTURE],
        ];
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

    /**
     * @dataProvider successfulRefundStatusProvider
     */
    public function testExecuteActionSuccessfullyWhenRefundTransactionExistsAndInactive(
        string $refundStatus,
        bool $isSuccessful,
        bool $isActive
    ): void {
        $stripeEvent = $this->createStripeEvent($refundStatus);

        /** @var StripeRefund $stripeRefund */
        $stripeRefund = $stripeEvent->data->object;

        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            $stripeEvent,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $refundTransaction = new PaymentTransaction();
        ReflectionUtil::setId($refundTransaction, 42);
        $refundTransaction->setAction(PaymentMethodInterface::REFUND);
        $refundTransaction->setSuccessful(true);
        $refundTransaction->setActive(false);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::REFUND, $paymentTransaction, ['reference' => $stripeRefund->id])
            ->willReturn($refundTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($refundTransaction);

        $this->loggerMock
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Unexpected state while handling the event "{eventType}" '
                . 'on the payment transaction #{purchasePaymentTransactionId}: '
                . 'the existing "refund" transaction #{refundPaymentTransactionId} is not active',
                [
                    'eventType' => 'refund.updated',
                    'purchasePaymentTransactionId' => $paymentTransaction->getId(),
                    'refundPaymentTransactionId' => $refundTransaction->getId(),
                ]
            );

        $expectedTransaction = clone $refundTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentRefundActionResult(successful: true, stripeRefund: $stripeRefund),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful($isSuccessful)
                ->setActive($isActive)
                ->setAmount($paymentTransaction->getAmount())
                ->setCurrency($paymentTransaction->getCurrency())
                ->setReference($stripeRefund->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_REASON, $stripeRefund->reason)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
                    $stripeRefund->payment_intent
                )
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id)
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $refundTransaction
        );
    }

    public function successfulRefundStatusProvider(): array
    {
        return [
            ['succeeded', true, false],
            ['pending', true, true],
            ['requires_action', true, true],
        ];
    }

    /**
     * @dataProvider successfulRefundStatusProvider
     */
    public function testExecuteActionSuccessfullyWhenRefundTransactionExistsAndActive(
        string $refundStatus,
        bool $isSuccessful,
        bool $isActive
    ): void {
        $stripeEvent = $this->createStripeEvent($refundStatus);

        /** @var StripeRefund $stripeRefund */
        $stripeRefund = $stripeEvent->data->object;

        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            $stripeEvent,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $refundTransaction = new PaymentTransaction();
        ReflectionUtil::setId($refundTransaction, 42);
        $refundTransaction->setAction(PaymentMethodInterface::REFUND);
        $refundTransaction->setSuccessful(true);
        $refundTransaction->setActive(true);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::REFUND, $paymentTransaction, ['reference' => $stripeRefund->id])
            ->willReturn($refundTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($refundTransaction);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $refundTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentRefundActionResult(successful: true, stripeRefund: $stripeRefund),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful($isSuccessful)
                ->setActive($isActive)
                ->setAmount($paymentTransaction->getAmount())
                ->setCurrency($paymentTransaction->getCurrency())
                ->setReference($stripeRefund->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_REASON, $stripeRefund->reason)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
                    $stripeRefund->payment_intent
                )
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id)
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $refundTransaction
        );
    }

    /**
     * @dataProvider successfulRefundStatusProvider
     */
    public function testExecuteActionSuccessfullyWhenRefundTransactionNew(
        string $refundStatus,
        bool $isSuccessful,
        bool $isActive
    ): void {
        $stripeEvent = $this->createStripeEvent($refundStatus);

        /** @var StripeRefund $stripeRefund */
        $stripeRefund = $stripeEvent->data->object;

        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            $stripeEvent,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $refundTransaction = new PaymentTransaction();
        $refundTransaction->setAction(PaymentMethodInterface::REFUND);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::REFUND, $paymentTransaction, ['reference' => $stripeRefund->id])
            ->willReturn($refundTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($refundTransaction);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $refundTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentRefundActionResult(successful: true, stripeRefund: $stripeRefund),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful($isSuccessful)
                ->setActive($isActive)
                ->setAmount($paymentTransaction->getAmount())
                ->setCurrency($paymentTransaction->getCurrency())
                ->setReference($stripeRefund->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_REASON, $stripeRefund->reason)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
                    $stripeRefund->payment_intent
                )
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id)
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $refundTransaction
        );
    }

    public function testExecuteActionSuccessfullyWhenRefundCanceled(): void
    {
        $stripeEvent = $this->createStripeEvent('canceled');

        /** @var StripeRefund $stripeRefund */
        $stripeRefund = $stripeEvent->data->object;

        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            $stripeEvent,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $refundTransaction = new PaymentTransaction();
        $refundTransaction->setAction(PaymentMethodInterface::REFUND);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::REFUND, $paymentTransaction, ['reference' => $stripeRefund->id])
            ->willReturn($refundTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($refundTransaction);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $refundTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentRefundActionResult(successful: true, stripeRefund: $stripeRefund),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful(false)
                ->setActive(false)
                ->setAmount($paymentTransaction->getAmount())
                ->setCurrency($paymentTransaction->getCurrency())
                ->setReference($stripeRefund->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_REASON, $stripeRefund->reason)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
                    $stripeRefund->payment_intent
                )
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id)
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $refundTransaction
        );
    }

    /**
     * @dataProvider successfulRefundStatusProvider
     */
    public function testExecuteActionSuccessfullyWhenRefundIsPartial(
        string $refundStatus,
        bool $isSuccessful,
        bool $isActive
    ): void {
        $stripeEvent = $this->createStripeEvent($refundStatus);

        /** @var StripeRefund $stripeRefund */
        $stripeRefund = $stripeEvent->data->object;
        $stripeRefund->amount = 11111;

        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentWebhookAction(
            $stripeEvent,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $refundTransaction = new PaymentTransaction();
        $refundTransaction->setAction(PaymentMethodInterface::REFUND);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('findOrCreateByPaymentTransaction')
            ->with(PaymentMethodInterface::REFUND, $paymentTransaction, ['reference' => $stripeRefund->id])
            ->willReturn($refundTransaction);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($refundTransaction);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $refundTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentRefundActionResult(successful: true, stripeRefund: $stripeRefund),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setSuccessful($isSuccessful)
                ->setActive($isActive)
                ->setAmount(111.11)
                ->setCurrency($paymentTransaction->getCurrency())
                ->setReference($stripeRefund->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_REASON, $stripeRefund->reason)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
                    $stripeRefund->payment_intent
                )
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id)
                ->addWebhookRequestLog($stripeEvent->toArray()),
            $refundTransaction
        );
    }
}
