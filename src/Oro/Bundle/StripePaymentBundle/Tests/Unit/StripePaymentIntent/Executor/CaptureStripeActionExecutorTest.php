<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\GenericStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\CaptureStripeActionExecutor;
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
final class CaptureStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const array TRANSACTION_OPTIONS = [StripePaymentIntentActionInterface::PAYMENT_INTENT_ID => 'pi_123'];

    private CaptureStripeActionExecutor $executor;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private MockObject&LoggingStripeClient $stripeClient;

    protected function setUp(): void
    {
        $stripeClientFactory = $this->createMock(StripeClientFactoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new CaptureStripeActionExecutor(
            $stripeClientFactory,
            new GenericStripeAmountConverter(),
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

    private function createSourceTransaction(string $action, array $transactionOptions): PaymentTransaction
    {
        $sourceTransaction = new PaymentTransaction();
        ReflectionUtil::setId($sourceTransaction, 12);
        $sourceTransaction->setAction($action);
        $sourceTransaction->setTransactionOptions($transactionOptions);

        return $sourceTransaction;
    }

    private function createCaptureTransaction(?PaymentTransaction $sourcePaymentTransaction): PaymentTransaction
    {
        $captureTransaction = new PaymentTransaction();
        ReflectionUtil::setId($captureTransaction, 123);
        $captureTransaction->setSourcePaymentTransaction($sourcePaymentTransaction);
        $captureTransaction->setAmount(123.45);
        $captureTransaction->setCurrency('USD');

        return $captureTransaction;
    }

    private function createStripePaymentIntent(string $status): StripePaymentIntent
    {
        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = $status;
        $stripePaymentIntent->amount_received = 12345;
        $stripePaymentIntent->currency = 'usd';

        return $stripePaymentIntent;
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::CAPTURE));
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
        $captureTransaction = $this->createCaptureTransaction(null);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CAPTURE,
            $this->stripePaymentElementConfig,
            $captureTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot capture the payment transaction #{paymentTransactionId}: no source payment transaction',
                [
                    'paymentTransactionId' => $captureTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNonAuthorizeSourceTransaction(): void
    {
        $sourceTransaction = $this->createSourceTransaction(PaymentMethodInterface::CHARGE, []);
        $captureTransaction = $this->createCaptureTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CAPTURE,
            $this->stripePaymentElementConfig,
            $captureTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoStripePaymentIntentId(): void
    {
        $sourceTransaction = $this->createSourceTransaction(PaymentMethodInterface::AUTHORIZE, []);
        $paymentTransaction = $this->createCaptureTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CAPTURE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot capture the payment transaction #{paymentTransactionId}: '
                . 'stripePaymentIntentId is not found in #{sourcePaymentTransactionId}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'sourcePaymentTransactionId' => $sourceTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrue(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $captureTransaction = $this->createCaptureTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CAPTURE,
            $this->stripePaymentElementConfig,
            $captureTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    /**
     * @dataProvider successfullyCapturedPaymentIntentStatusProvider
     */
    public function testExecuteActionSuccessfullyCapturesPaymentIntent(string $stripePaymentIntentStatus): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $captureTransaction = $this->createCaptureTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CAPTURE,
            $this->stripePaymentElementConfig,
            $captureTransaction
        );

        $stripePaymentIntent = $this->createStripePaymentIntent($stripePaymentIntentStatus);

        $requestArgs = [
            $stripePaymentIntent->id,
            ['amount_to_capture' => 12345],
        ];
        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCapture',
            $requestArgs
        );
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($captureTransaction);

        $stripePaymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $stripePaymentIntentService;
        $stripePaymentIntentService
            ->expects(self::once())
            ->method('capture')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $captureTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CAPTURE)
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id),
            $captureTransaction
        );
        self::assertFalse($sourceTransaction->isActive());
    }

    public function successfullyCapturedPaymentIntentStatusProvider(): array
    {
        // More about Payment Intent statuses:
        // https://docs.stripe.com/payments/paymentintents/lifecycle#intent-statuses
        return [['succeeded'], ['processing']];
    }

    /**
     * @dataProvider failedCapturePaymentIntentProvider
     */
    public function testExecuteActionHandlesFailedPaymentIntentCapture(StripePaymentIntent $stripePaymentIntent): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $captureTransaction = $this->createCaptureTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CAPTURE,
            $this->stripePaymentElementConfig,
            $captureTransaction
        );

        $requestArgs = [
            $stripePaymentIntent->id,
            ['amount_to_capture' => 12345],
        ];
        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCapture',
            $requestArgs
        );

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($captureTransaction);

        $stripePaymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $stripePaymentIntentService;
        $stripePaymentIntentService
            ->expects(self::once())
            ->method('capture')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $captureTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CAPTURE)
                ->setSuccessful(false)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id),
            $captureTransaction
        );
        self::assertTrue($sourceTransaction->isActive());
    }

    public function failedCapturePaymentIntentProvider(): \Generator
    {
        $stripePaymentIntent = $this->createStripePaymentIntent('canceled');

        yield 'status is canceled' => [$stripePaymentIntent];

        $stripePaymentIntent = $this->createStripePaymentIntent('succeeded');
        $stripePaymentIntent->amount_received = 12340;

        yield 'amount is not valid' => [$stripePaymentIntent];

        $stripePaymentIntent = $this->createStripePaymentIntent('succeeded');
        $stripePaymentIntent->currency = 'eur';

        yield 'currency is not valid' => [$stripePaymentIntent];
    }

    public function testExecuteActionWhenEventDispatcherModifiesRequest(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $captureTransaction = $this->createCaptureTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CAPTURE,
            $this->stripePaymentElementConfig,
            $captureTransaction
        );

        $stripePaymentIntent = $this->createStripePaymentIntent('succeeded');

        $requestArgs = [
            $stripePaymentIntent->id,
            ['amount_to_capture' => 12345],
        ];

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($captureTransaction);

        $stripePaymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $stripePaymentIntentService;

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(
                    static function (StripePaymentIntentActionBeforeRequestEvent $beforeRequestEvent) use (
                        $stripeAction,
                        $requestArgs,
                        $stripePaymentIntentService,
                        $stripePaymentIntent
                    ) {
                        self::assertSame($stripeAction, $beforeRequestEvent->getStripeAction());
                        self::assertEquals('paymentIntentsCapture', $beforeRequestEvent->getRequestName());
                        self::assertSame($requestArgs, $beforeRequestEvent->getRequestArgs());

                        $requestArgs[1]['metadata']['sample_key'] = 'sample_value';
                        $beforeRequestEvent->setRequestArgs($requestArgs);

                        $stripePaymentIntentService
                            ->expects(self::once())
                            ->method('capture')
                            ->with(...$beforeRequestEvent->getRequestArgs())
                            ->willReturn($stripePaymentIntent);

                        return true;
                    }
                )
            );

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $captureTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CAPTURE)
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id),
            $captureTransaction
        );
        self::assertFalse($sourceTransaction->isActive());
    }
}
