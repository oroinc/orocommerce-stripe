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
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\RefundStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentRefundActionResult;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Refund as StripeRefund;
use Stripe\Service\RefundService as StripeRefundService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class RefundStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const string PAYMENT_INTENT_ID = 'pi_123';
    private const array TRANSACTION_OPTIONS = [
        StripePaymentIntentActionInterface::PAYMENT_INTENT_ID => self::PAYMENT_INTENT_ID,
    ];

    private RefundStripeActionExecutor $executor;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private MockObject&LoggingStripeClient $stripeClient;

    protected function setUp(): void
    {
        $stripeClientFactory = $this->createMock(StripeClientFactoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new RefundStripeActionExecutor(
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

    private function createPurchaseTransaction(?PaymentTransaction $sourcePaymentTransaction): PaymentTransaction
    {
        $purchaseTransaction = new PaymentTransaction();
        ReflectionUtil::setId($purchaseTransaction, 123);
        if ($sourcePaymentTransaction !== null) {
            $purchaseTransaction->setSourcePaymentTransaction($sourcePaymentTransaction);
        }
        $purchaseTransaction->setAmount(123.45);
        $purchaseTransaction->setCurrency('USD');

        return $purchaseTransaction;
    }

    private function createStripeRefund(string $status): StripeRefund
    {
        $stripeRefund = new StripeRefund('re_123');
        $stripeRefund->status = $status;
        $stripeRefund->amount = 12345;
        $stripeRefund->currency = 'usd';
        $stripeRefund->payment_intent = self::PAYMENT_INTENT_ID;

        return $stripeRefund;
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::REFUND));
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotSupportedAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::AUTHORIZE));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedActionName(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::CAPTURE,
            self::TRANSACTION_OPTIONS
        );
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $this->createPurchaseTransaction($sourceTransaction)
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoSourcePaymentTransaction(): void
    {
        $purchaseTransaction = $this->createPurchaseTransaction(null);
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::REFUND,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot refund the payment transaction #{paymentTransactionId}: no source payment transaction',
                [
                    'paymentTransactionId' => $purchaseTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenSourcePaymentTransactionActionNotSupported(): void
    {
        $sourceTransaction = $this->createSourceTransaction(PaymentMethodInterface::CANCEL, self::TRANSACTION_OPTIONS);
        $purchaseTransaction = $this->createPurchaseTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::REFUND,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoStripePaymentIntentId(): void
    {
        $sourceTransaction = $this->createSourceTransaction(PaymentMethodInterface::PURCHASE, []);
        $purchaseTransaction = $this->createPurchaseTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::REFUND,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot refund the payment transaction #{paymentTransactionId}: '
                . 'stripePaymentIntentId is not found in #{sourcePaymentTransactionId}',
                [
                    'paymentTransactionId' => $purchaseTransaction->getId(),
                    'sourcePaymentTransactionId' => $sourceTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    /**
     * @dataProvider paymentTransactionSupportedActionsProvider
     */
    public function testIsApplicableForActionReturnsTrue(string $action): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            $action,
            self::TRANSACTION_OPTIONS
        );
        $purchaseTransaction = $this->createPurchaseTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::REFUND,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
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

    /**
     * @dataProvider successfullyRefundedStatusProvider
     */
    public function testExecuteActionSuccessfullyRefundsPaymentIntent(string $stripeRefundStatus): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::CAPTURE,
            self::TRANSACTION_OPTIONS
        );
        $purchaseTransaction = $this->createPurchaseTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::REFUND,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $stripeRefund = $this->createStripeRefund($stripeRefundStatus);

        $requestArgs = [
            [
                'payment_intent' => self::PAYMENT_INTENT_ID,
                'reason' => 'requested_by_customer',
                'amount' => 12345,
                'metadata' => [
                    'payment_transaction_access_identifier' => $purchaseTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $purchaseTransaction->getAccessToken(),
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'refundsCreate',
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

        $stripeRefundService = $this->createMock(StripeRefundService::class);
        $this->stripeClient->refunds = $stripeRefundService;
        $stripeRefundService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripeRefund);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $purchaseTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentRefundActionResult(successful: true, stripeRefund: $stripeRefund),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::REFUND)
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripeRefund->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, self::PAYMENT_INTENT_ID)
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id),
            $purchaseTransaction
        );
        self::assertFalse($sourceTransaction->isActive());
    }

    public function successfullyRefundedStatusProvider(): array
    {
        return [['succeeded'], ['pending'], ['requires_action']];
    }

    /**
     * @dataProvider failedRefundProvider
     */
    public function testExecuteActionHandlesFailedPaymentIntentRefund(StripeRefund $stripeRefund): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::CAPTURE,
            self::TRANSACTION_OPTIONS
        );
        $purchaseTransaction = $this->createPurchaseTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::REFUND,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $requestArgs = [
            [
                'payment_intent' => self::PAYMENT_INTENT_ID,
                'reason' => 'requested_by_customer',
                'amount' => 12345,
                'metadata' => [
                    'payment_transaction_access_identifier' => $purchaseTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $purchaseTransaction->getAccessToken(),
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'refundsCreate',
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

        $stripeRefundService = $this->createMock(StripeRefundService::class);
        $this->stripeClient->refunds = $stripeRefundService;
        $stripeRefundService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripeRefund);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $purchaseTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentRefundActionResult(successful: false, stripeRefund: $stripeRefund),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::REFUND)
                ->setSuccessful(false)
                ->setActive(false)
                ->setReference($stripeRefund->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, self::PAYMENT_INTENT_ID)
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id),
            $purchaseTransaction
        );
        self::assertTrue($sourceTransaction->isActive());
    }

    public function failedRefundProvider(): \Generator
    {
        $stripeRefund = $this->createStripeRefund('failed');

        yield 'status is failed' => [$stripeRefund];

        $stripeRefund = $this->createStripeRefund('succeeded');
        $stripeRefund->amount = 12340;

        yield 'amount is not valid' => [$stripeRefund];

        $stripeRefund = $this->createStripeRefund('succeeded');
        $stripeRefund->currency = 'eur';

        yield 'currency is not valid' => [$stripeRefund];
    }

    public function testExecuteActionSuccessfullyRefundsPaymentIntentWithCustomRefundReason(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::CHARGE,
            self::TRANSACTION_OPTIONS
        );
        $purchaseTransaction = $this->createPurchaseTransaction($sourceTransaction);
        $purchaseTransaction->addTransactionOption(StripePaymentIntentActionInterface::REFUND_REASON, 'fraudulent');

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::REFUND,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $stripeRefund = $this->createStripeRefund('succeeded');

        $requestArgs = [
            [
                'payment_intent' => self::PAYMENT_INTENT_ID,
                'reason' => $purchaseTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::REFUND_REASON
                ),
                'amount' => 12345,
                'metadata' => [
                    'payment_transaction_access_identifier' => $purchaseTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $purchaseTransaction->getAccessToken(),
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'refundsCreate',
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

        $stripeRefundService = $this->createMock(StripeRefundService::class);
        $this->stripeClient->refunds = $stripeRefundService;
        $stripeRefundService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripeRefund);

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $purchaseTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentRefundActionResult(successful: true, stripeRefund: $stripeRefund),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::REFUND)
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripeRefund->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, self::PAYMENT_INTENT_ID)
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::REFUND_REASON,
                    $purchaseTransaction->getTransactionOption(
                        StripePaymentIntentActionInterface::REFUND_REASON
                    )
                ),
            $purchaseTransaction
        );
        self::assertFalse($sourceTransaction->isActive());
    }

    public function testExecuteActionWhenEventDispatcherModifiesRequest(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::CHARGE,
            self::TRANSACTION_OPTIONS
        );

        $paymentTransaction = $this->createPurchaseTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::REFUND,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $stripeRefund = $this->createStripeRefund('succeeded');

        $requestArgs = [
            [
                'payment_intent' => self::PAYMENT_INTENT_ID,
                'reason' => 'requested_by_customer',
                'amount' => 12345,
                'metadata' => [
                    'payment_transaction_access_identifier' => $paymentTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $paymentTransaction->getAccessToken(),
                ],
            ],
        ];

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($paymentTransaction);

        $stripeRefundService = $this->createMock(StripeRefundService::class);
        $this->stripeClient->refunds = $stripeRefundService;

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(
                    static function (StripePaymentIntentActionBeforeRequestEvent $beforeRequestEvent) use (
                        $stripeAction,
                        $requestArgs,
                        $stripeRefundService,
                        $stripeRefund
                    ) {
                        self::assertSame($stripeAction, $beforeRequestEvent->getStripeAction());
                        self::assertEquals('refundsCreate', $beforeRequestEvent->getRequestName());
                        self::assertSame($requestArgs, $beforeRequestEvent->getRequestArgs());

                        $requestArgs[1]['metadata']['sample_key'] = 'sample_value';
                        $beforeRequestEvent->setRequestArgs($requestArgs);

                        $stripeRefundService
                            ->expects(self::once())
                            ->method('create')
                            ->with(...$beforeRequestEvent->getRequestArgs())
                            ->willReturn($stripeRefund);

                        return true;
                    }
                )
            );

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $paymentTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentRefundActionResult(successful: true, stripeRefund: $stripeRefund),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::REFUND)
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($stripeRefund->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, self::PAYMENT_INTENT_ID)
                ->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id),
            $paymentTransaction
        );
        self::assertFalse($sourceTransaction->isActive());
    }
}
