<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\CancelStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\PaymentIntent as StripePaymentIntent;
use Stripe\Service\PaymentIntentService as StripePaymentIntentService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class CancelStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private CancelStripeActionExecutor $executor;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private PaymentTransaction $paymentTransaction;

    private PaymentTransaction $sourcePaymentTransaction;

    private MockObject&LoggingStripeClient $stripeClient;

    protected function setUp(): void
    {
        $stripeClientFactory = $this->createMock(StripeClientFactoryInterface::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->cancelPaymentIntentsMethodAction = $this->createMock(
            StripePaymentIntentActionExecutorInterface::class
        );
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new CancelStripeActionExecutor(
            $stripeClientFactory,
            $this->eventDispatcher
        );

        $this->setUpLoggerMock($this->executor);

        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripeClient = $this->createMock(LoggingStripeClient::class);
        $stripeClientFactory
            ->method('createStripeClient')
            ->with($this->stripePaymentElementConfig)
            ->willReturn($this->stripeClient);
    }

    private function createCancelTransaction(): PaymentTransaction
    {
        $sourceTransaction = new PaymentTransaction();
        ReflectionUtil::setId($sourceTransaction, 12);
        $sourceTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $sourceTransaction->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, 'pi_123');

        $cancelTransaction = new PaymentTransaction();
        ReflectionUtil::setId($cancelTransaction, 123);
        $cancelTransaction->setSourcePaymentTransaction($sourceTransaction);

        return $cancelTransaction;
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::CANCEL));
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotSupportedAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::AUTHORIZE));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedActionName(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            new PaymentTransaction()
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoSourcePaymentTransaction(): void
    {
        $cancelTransaction = new PaymentTransaction();
        ReflectionUtil::setId($cancelTransaction, 123);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CANCEL,
            $this->stripePaymentElementConfig,
            $cancelTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot cancel the payment transaction #{paymentTransactionId}: no source payment transaction',
                [
                    'paymentTransactionId' => $cancelTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNonAuthorizeSourceTransaction(): void
    {
        $sourceTransaction = new PaymentTransaction();
        ReflectionUtil::setId($sourceTransaction, 12);
        $sourceTransaction->setAction(PaymentMethodInterface::CHARGE);

        $cancelTransaction = new PaymentTransaction();
        ReflectionUtil::setId($cancelTransaction, 123);
        $cancelTransaction->setSourcePaymentTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CANCEL,
            $this->stripePaymentElementConfig,
            $cancelTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoStripePaymentIntentId(): void
    {
        $sourceTransaction = new PaymentTransaction();
        ReflectionUtil::setId($sourceTransaction, 12);
        $sourceTransaction->setAction(PaymentMethodInterface::AUTHORIZE);

        $cancelTransaction = new PaymentTransaction();
        ReflectionUtil::setId($cancelTransaction, 123);
        $cancelTransaction->setSourcePaymentTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CANCEL,
            $this->stripePaymentElementConfig,
            $cancelTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot cancel the payment transaction #{paymentTransactionId}: '
                . 'stripePaymentIntentId is not found in #{sourcePaymentTransactionId}',
                [
                    'paymentTransactionId' => $cancelTransaction->getId(),
                    'sourcePaymentTransactionId' => $sourceTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrue(): void
    {
        $cancelTransaction = $this->createCancelTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CANCEL,
            $this->stripePaymentElementConfig,
            $cancelTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionSuccessfullyCancelsPaymentIntent(): void
    {
        $cancelTransaction = $this->createCancelTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CANCEL,
            $this->stripePaymentElementConfig,
            $cancelTransaction
        );

        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = 'canceled';

        $requestArgs = [
            $stripePaymentIntent->id,
            ['cancellation_reason' => 'requested_by_customer'],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCancel',
            $requestArgs
        );
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($cancelTransaction);

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('cancel')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $cancelTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CANCEL)
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id),
            $cancelTransaction
        );
    }

    public function testExecuteActionHandlesFailedPaymentIntentCancellation(): void
    {
        $cancelTransaction = $this->createCancelTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CANCEL,
            $this->stripePaymentElementConfig,
            $cancelTransaction
        );

        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = 'succeeded';

        $requestArgs = [
            $stripePaymentIntent->id,
            ['cancellation_reason' => 'requested_by_customer'],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCancel',
            $requestArgs
        );
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($cancelTransaction);

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('cancel')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $cancelTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CANCEL)
                ->setSuccessful(false)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id),
            $cancelTransaction
        );
    }

    public function testExecuteActionSuccessfullyCancelsPaymentIntentWithCustomCancelReason(): void
    {
        $cancelTransaction = $this->createCancelTransaction();
        $cancelTransaction->addTransactionOption(StripePaymentIntentActionInterface::CANCEL_REASON, 'fraudulent');

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CANCEL,
            $this->stripePaymentElementConfig,
            $cancelTransaction
        );

        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = 'canceled';

        $requestArgs = [
            $stripePaymentIntent->id,
            [
                'cancellation_reason' => $cancelTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::CANCEL_REASON
                ),
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCancel',
            $requestArgs
        );
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($cancelTransaction);

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('cancel')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $cancelTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CANCEL)
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id),
            $cancelTransaction
        );
    }

    public function testExecuteActionWhenEventDispatcherModifiesRequest(): void
    {
        $cancelTransaction = $this->createCancelTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CANCEL,
            $this->stripePaymentElementConfig,
            $cancelTransaction
        );

        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = 'canceled';

        $requestArgs = [
            $stripePaymentIntent->id,
            ['cancellation_reason' => 'requested_by_customer'],
        ];

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($cancelTransaction);

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(
                    static function (StripePaymentIntentActionBeforeRequestEvent $beforeRequestEvent) use (
                        $stripeAction,
                        $requestArgs,
                        $paymentIntentService,
                        $stripePaymentIntent
                    ) {
                        self::assertSame($stripeAction, $beforeRequestEvent->getStripeAction());
                        self::assertEquals('paymentIntentsCancel', $beforeRequestEvent->getRequestName());
                        self::assertSame($requestArgs, $beforeRequestEvent->getRequestArgs());

                        $requestArgs[1]['metadata']['sample_key'] = 'sample_value';
                        $beforeRequestEvent->setRequestArgs($requestArgs);

                        $paymentIntentService
                            ->expects(self::once())
                            ->method('cancel')
                            ->with(...$beforeRequestEvent->getRequestArgs())
                            ->willReturn($stripePaymentIntent);

                        return true;
                    }
                )
            );

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $cancelTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CANCEL)
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id),
            $cancelTransaction
        );
    }
}
