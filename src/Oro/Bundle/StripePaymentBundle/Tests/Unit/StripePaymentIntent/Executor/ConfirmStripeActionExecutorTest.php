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
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\ConfirmStripeActionExecutor;
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
final class ConfirmStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const array TRANSACTION_OPTIONS = [StripePaymentIntentActionInterface::PAYMENT_INTENT_ID => 'pi_123'];

    private ConfirmStripeActionExecutor $executor;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private MockObject&LoggingStripeClient $stripeClient;

    protected function setUp(): void
    {
        $stripeClientFactory = $this->createMock(StripeClientFactoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new ConfirmStripeActionExecutor(
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

    private function createPurchaseTransaction(array $transactionOptions): PaymentTransaction
    {
        $purchaseTransaction = new PaymentTransaction();
        ReflectionUtil::setId($purchaseTransaction, 123);
        $purchaseTransaction->setAction(PaymentMethodInterface::PURCHASE);
        $purchaseTransaction->setAmount(123.45);
        $purchaseTransaction->setCurrency('USD');
        $purchaseTransaction->setTransactionOptions($transactionOptions);

        return $purchaseTransaction;
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue(
            $this->executor->isSupportedByActionName(ConfirmStripeActionExecutor::ACTION_NAME)
        );
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
            $this->createPurchaseTransaction(self::TRANSACTION_OPTIONS)
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoStripePaymentIntentId(): void
    {
        $purchaseTransaction = $this->createPurchaseTransaction([]);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot confirm the payment transaction #{paymentTransactionId}: stripePaymentIntentId is not found',
                [
                    'paymentTransactionId' => $purchaseTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrue(): void
    {
        $purchaseTransaction = $this->createPurchaseTransaction(self::TRANSACTION_OPTIONS);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    /**
     * @dataProvider paymentIntentStatusProvider
     */
    public function testExecuteActionSuccessfullyConfirmsPaymentIntent(string $paymentIntentStatus): void
    {
        $purchaseTransaction = $this->createPurchaseTransaction(self::TRANSACTION_OPTIONS);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = $paymentIntentStatus;
        $stripePaymentIntent->amount = 12345;
        $stripePaymentIntent->currency = 'usd';

        $requestArgs = [$stripePaymentIntent->id];
        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsRetrieve',
            $requestArgs
        );

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($purchaseTransaction);

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('retrieve')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $purchaseTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::PURCHASE)
                ->setSuccessful(true)
                ->setActive(false),
            $purchaseTransaction
        );
    }

    public function paymentIntentStatusProvider(): array
    {
        // More about Payment Intent statuses:
        // https://docs.stripe.com/payments/paymentintents/lifecycle#intent-statuses
        return [['succeeded'], ['processing']];
    }

    public function testExecuteActionSuccessfullyConfirmsPaymentIntentAndRequiresCapture(): void
    {
        $purchaseTransaction = $this->createPurchaseTransaction(self::TRANSACTION_OPTIONS);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = 'requires_capture';
        $stripePaymentIntent->amount = 12345;
        $stripePaymentIntent->currency = 'usd';

        $requestArgs = [$stripePaymentIntent->id];
        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsRetrieve',
            $requestArgs
        );

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($purchaseTransaction);

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('retrieve')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $purchaseTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::PURCHASE)
                ->setSuccessful(true)
                ->setActive(true),
            $purchaseTransaction
        );
    }

    /**
     * @dataProvider failedConfirmPaymentIntentProvider
     */
    public function testExecuteActionHandlesFailedPaymentIntentConfirmation(
        StripePaymentIntent $stripePaymentIntent
    ): void {
        $purchaseTransaction = $this->createPurchaseTransaction(self::TRANSACTION_OPTIONS);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $requestArgs = [$stripePaymentIntent->id];
        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsRetrieve',
            $requestArgs
        );

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($purchaseTransaction);

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('retrieve')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $purchaseTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::PURCHASE)
                ->setSuccessful(false)
                ->setActive(false),
            $purchaseTransaction
        );
    }

    public function failedConfirmPaymentIntentProvider(): \Generator
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'canceled';
        $paymentIntent->amount = 12345;
        $paymentIntent->currency = 'usd';

        yield 'status is canceled' => [$paymentIntent];

        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'succeeded';
        $paymentIntent->amount = 12340;
        $paymentIntent->currency = 'usd';

        yield 'amount is not valid' => [$paymentIntent];

        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'succeeded';
        $paymentIntent->amount = 12345;
        $paymentIntent->currency = 'eur';

        yield 'currency is not valid' => [$paymentIntent];
    }

    public function testExecuteActionWhenEventDispatcherModifiesRequest(): void
    {
        $purchaseTransaction = $this->createPurchaseTransaction(self::TRANSACTION_OPTIONS);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'succeeded';
        $paymentIntent->amount = 12345;
        $paymentIntent->currency = 'usd';

        $requestArgs = [$paymentIntent->id];
        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsRetrieve',
            $requestArgs
        );

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($purchaseTransaction);

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
                        $paymentIntent
                    ) {
                        self::assertSame($stripeAction, $beforeRequestEvent->getStripeAction());
                        self::assertEquals('paymentIntentsRetrieve', $beforeRequestEvent->getRequestName());
                        self::assertSame($requestArgs, $beforeRequestEvent->getRequestArgs());

                        $requestArgs[1]['metadata']['sample_key'] = 'sample_value';
                        $beforeRequestEvent->setRequestArgs($requestArgs);

                        $paymentIntentService
                            ->expects(self::once())
                            ->method('retrieve')
                            ->with(...$beforeRequestEvent->getRequestArgs())
                            ->willReturn($paymentIntent);

                        return true;
                    }
                )
            );

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $purchaseTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $paymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::PURCHASE)
                ->setSuccessful(true)
                ->setActive(false),
            $purchaseTransaction
        );
    }
}
