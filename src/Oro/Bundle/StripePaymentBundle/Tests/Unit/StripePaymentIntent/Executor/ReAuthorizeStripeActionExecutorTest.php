<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\GenericStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\ReAuthorizeStripeActionExecutor;
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
final class ReAuthorizeStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const array TRANSACTION_OPTIONS = [
        ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED => true,
        StripePaymentIntentActionInterface::CUSTOMER_ID => 'cus_123',
        StripePaymentIntentActionInterface::PAYMENT_METHOD_ID => 'pm_123',
    ];

    private ReAuthorizeStripeActionExecutor $executor;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    private MockObject&StripePaymentIntentActionExecutorInterface $cancelPaymentIntentsMethodAction;

    private MockObject&StripeAmountConverterInterface $stripeAmountConverter;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private MockObject&LoggingStripeClient $stripeClient;

    protected function setUp(): void
    {
        $stripeClientFactory = $this->createMock(StripeClientFactoryInterface::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->cancelPaymentIntentsMethodAction = $this->createMock(
            StripePaymentIntentActionExecutorInterface::class
        );
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new ReAuthorizeStripeActionExecutor(
            $stripeClientFactory,
            $this->paymentTransactionProvider,
            $this->cancelPaymentIntentsMethodAction,
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
        $sourceTransaction->setActive(true);
        $sourceTransaction->setTransactionOptions($transactionOptions);

        return $sourceTransaction;
    }

    private function createReAuthorizeTransaction(?PaymentTransaction $sourceTransaction): PaymentTransaction
    {
        $reAuthorizeTransaction = new PaymentTransaction();
        ReflectionUtil::setId($reAuthorizeTransaction, 123);
        if ($sourceTransaction !== null) {
            $reAuthorizeTransaction->setSourcePaymentTransaction($sourceTransaction);
        }

        return $reAuthorizeTransaction;
    }

    private function createAuthorizeTransaction(PaymentTransaction $sourceTransaction): PaymentTransaction
    {
        $authorizeTransaction = new PaymentTransaction();
        $authorizeTransaction->setSourcePaymentTransaction($sourceTransaction);
        $authorizeTransaction->setAccessIdentifier('sample_identifier');
        $authorizeTransaction->setAccessToken('sample_token');
        $authorizeTransaction->setAmount(123.45);
        $authorizeTransaction->setCurrency('USD');

        return $authorizeTransaction;
    }

    private function createCancelTransaction(PaymentTransaction $sourceTransaction): PaymentTransaction
    {
        $cancelTransaction = new PaymentTransaction();
        $cancelTransaction->setSourcePaymentTransaction($sourceTransaction);

        return $cancelTransaction;
    }

    private function createPaymentIntent(string $status): StripePaymentIntent
    {
        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = $status;
        $stripePaymentIntent->client_secret = 'pi_123_secret_123';
        $stripePaymentIntent->payment_method = 'pm_123';
        $stripePaymentIntent->customer = 'cus_123';

        return $stripePaymentIntent;
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::RE_AUTHORIZE));
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotSupportedAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::AUTHORIZE));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedActionName(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $this->createReAuthorizeTransaction($sourceTransaction)
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoSourcePaymentTransaction(): void
    {
        $reAuthorizeTransaction = $this->createReAuthorizeTransaction(null);
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $reAuthorizeTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot re-authorize the payment transaction #{reAuthorizePaymentTransactionId}: '
                . 'no source payment transaction',
                [
                    'reAuthorizePaymentTransactionId' => $reAuthorizeTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNonAuthorizeSourceTransaction(): void
    {
        $sourceTransaction = $this->createSourceTransaction(PaymentMethodInterface::CHARGE, self::TRANSACTION_OPTIONS);
        $paymentTransaction = $this->createReAuthorizeTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenReAuthorizationDisabled(): void
    {
        $sourceTransaction = $this->createSourceTransaction(PaymentMethodInterface::CHARGE, []);
        $paymentTransaction = $this->createReAuthorizeTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoStripeCustomerId(): void
    {
        $sourceTransaction = new PaymentTransaction();
        ReflectionUtil::setId($sourceTransaction, 12);
        $sourceTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $sourceTransaction->addTransactionOption(
            ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED,
            true
        );

        $reAuthorizeTransaction = $this->createReAuthorizeTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $reAuthorizeTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot re-authorize the payment transaction #{reAuthorizePaymentTransactionId}: '
                . 'stripeCustomerId is not found in #{sourcePaymentTransactionId}',
                [
                    'reAuthorizePaymentTransactionId' => $reAuthorizeTransaction->getId(),
                    'sourcePaymentTransactionId' => $sourceTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoStripePaymentMethodId(): void
    {
        $sourceTransaction = new PaymentTransaction();
        ReflectionUtil::setId($sourceTransaction, 12);
        $sourceTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $sourceTransaction->addTransactionOption(
            ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED,
            true
        );
        $sourceTransaction->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, 'cus_123');

        $reAuthorizeTransaction = $this->createReAuthorizeTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $reAuthorizeTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot re-authorize the payment transaction #{reAuthorizePaymentTransactionId}: '
                . 'stripePaymentMethodId is not found in #{sourcePaymentTransactionId}',
                [
                    'reAuthorizePaymentTransactionId' => $reAuthorizeTransaction->getId(),
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
        $paymentTransaction = $this->createReAuthorizeTransaction($sourceTransaction);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionSuccessfullyCreatesPaymentIntent(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $reAuthorizeTransaction = $this->createReAuthorizeTransaction($sourceTransaction);
        $cancelTransaction = $this->createCancelTransaction($sourceTransaction);
        $authorizeTransaction = $this->createAuthorizeTransaction($sourceTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(2))
            ->method('createPaymentTransactionByParentTransaction')
            ->withConsecutive(
                [PaymentMethodInterface::CANCEL, $sourceTransaction],
                [PaymentMethodInterface::AUTHORIZE, $sourceTransaction],
            )
            ->willReturn($cancelTransaction, $authorizeTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(4))
            ->method('savePaymentTransaction')
            ->withConsecutive(
                [$cancelTransaction],
                [$authorizeTransaction],
                [$authorizeTransaction],
                [$cancelTransaction]
            );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $reAuthorizeTransaction
        );

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $authorizeTransaction->getCurrency(),
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => $sourceTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID
                ),
                'customer' => $sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID),
                'metadata' => [
                    'payment_transaction_access_identifier' => $authorizeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $authorizeTransaction->getAccessToken(),
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCreate',
            $requestArgs
        );
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($authorizeTransaction);

        $stripePaymentIntent = $this->createPaymentIntent('requires_capture');

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->cancelPaymentIntentsMethodAction
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new StripePaymentIntentAction(
                    actionName: PaymentMethodInterface::CANCEL,
                    stripePaymentIntentConfig: $stripeAction->getStripeClientConfig(),
                    paymentTransaction: $cancelTransaction
                )
            )
            ->willReturn(
                new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent)
            );

        $expectedTransaction = clone $authorizeTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::AUTHORIZE)
                ->setSuccessful(true)
                ->setActive(true)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, true)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $stripePaymentIntent->customer)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentIntent->payment_method
                ),
            $authorizeTransaction
        );
        self::assertTrue($reAuthorizeTransaction->isSuccessful());
        self::assertFalse($sourceTransaction->isActive());
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionHandlesFailedPaymentIntentCreation(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $reAuthorizeTransaction = $this->createReAuthorizeTransaction($sourceTransaction);
        $cancelTransaction = $this->createCancelTransaction($sourceTransaction);
        $authorizeTransaction = $this->createAuthorizeTransaction($sourceTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(2))
            ->method('createPaymentTransactionByParentTransaction')
            ->withConsecutive(
                [PaymentMethodInterface::CANCEL, $sourceTransaction],
                [PaymentMethodInterface::AUTHORIZE, $sourceTransaction],
            )
            ->willReturn($cancelTransaction, $authorizeTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(3))
            ->method('savePaymentTransaction')
            ->withConsecutive(
                [$cancelTransaction],
                [$authorizeTransaction],
                [$authorizeTransaction]
            );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $reAuthorizeTransaction
        );

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $authorizeTransaction->getCurrency(),
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => $sourceTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID
                ),
                'customer' => $sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID),
                'metadata' => [
                    'payment_transaction_access_identifier' => $authorizeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $authorizeTransaction->getAccessToken(),
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCreate',
            $requestArgs
        );
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($authorizeTransaction);

        $stripePaymentIntent = $this->createPaymentIntent('requires_payment_method');

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->cancelPaymentIntentsMethodAction
            ->expects(self::never())
            ->method('executeAction');

        $expectedTransaction = clone $authorizeTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::AUTHORIZE)
                ->setSuccessful(false)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, true)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $stripePaymentIntent->customer)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentIntent->payment_method
                ),
            $authorizeTransaction
        );
        self::assertFalse($reAuthorizeTransaction->isSuccessful());
        self::assertTrue($sourceTransaction->isActive());
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionHandlesFailedCancelAction(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $reAuthorizeTransaction = $this->createReAuthorizeTransaction($sourceTransaction);
        $cancelTransaction = $this->createCancelTransaction($sourceTransaction);
        $authorizeTransaction = $this->createAuthorizeTransaction($sourceTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(2))
            ->method('createPaymentTransactionByParentTransaction')
            ->withConsecutive(
                [PaymentMethodInterface::CANCEL, $sourceTransaction],
                [PaymentMethodInterface::AUTHORIZE, $sourceTransaction],
            )
            ->willReturn($cancelTransaction, $authorizeTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(4))
            ->method('savePaymentTransaction')
            ->withConsecutive(
                [$cancelTransaction],
                [$authorizeTransaction],
                [$authorizeTransaction],
                [$cancelTransaction]
            );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $reAuthorizeTransaction
        );

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $authorizeTransaction->getCurrency(),
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => $sourceTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID
                ),
                'customer' => $sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID),
                'metadata' => [
                    'payment_transaction_access_identifier' => $authorizeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $authorizeTransaction->getAccessToken(),
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCreate',
            $requestArgs
        );
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($authorizeTransaction);

        $stripePaymentIntent = $this->createPaymentIntent('requires_capture');

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->cancelPaymentIntentsMethodAction
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new StripePaymentIntentAction(
                    actionName: PaymentMethodInterface::CANCEL,
                    stripePaymentIntentConfig: $stripeAction->getStripeClientConfig(),
                    paymentTransaction: $cancelTransaction
                )
            )
            ->willReturn(
                new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent)
            );

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to re-authorize the payment transaction #{reAuthorizePaymentTransactionId}: '
                . 'failed to cancel the source payment transaction #{sourcePaymentTransactionId}',
                [
                    'reAuthorizePaymentTransactionId' => $reAuthorizeTransaction->getId(),
                    'sourcePaymentTransactionId' => $sourceTransaction->getId(),
                ]
            );

        $expectedTransaction = clone $authorizeTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::AUTHORIZE)
                ->setSuccessful(true)
                ->setActive(true)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, true)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $stripePaymentIntent->customer)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentIntent->payment_method
                ),
            $authorizeTransaction
        );
        self::assertTrue($reAuthorizeTransaction->isSuccessful());
        self::assertFalse($sourceTransaction->isActive());
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionHandlesExceptionDuringCancel(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $reAuthorizeTransaction = $this->createReAuthorizeTransaction($sourceTransaction);
        $cancelTransaction = $this->createCancelTransaction($sourceTransaction);
        $authorizeTransaction = $this->createAuthorizeTransaction($sourceTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(2))
            ->method('createPaymentTransactionByParentTransaction')
            ->withConsecutive(
                [PaymentMethodInterface::CANCEL, $sourceTransaction],
                [PaymentMethodInterface::AUTHORIZE, $sourceTransaction],
            )
            ->willReturn($cancelTransaction, $authorizeTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(4))
            ->method('savePaymentTransaction')
            ->withConsecutive(
                [$cancelTransaction],
                [$authorizeTransaction],
                [$authorizeTransaction],
                [$cancelTransaction]
            );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $reAuthorizeTransaction
        );

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $authorizeTransaction->getCurrency(),
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => $sourceTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID
                ),
                'customer' => $sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID),
                'metadata' => [
                    'payment_transaction_access_identifier' => $authorizeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $authorizeTransaction->getAccessToken(),
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCreate',
            $requestArgs
        );
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($authorizeTransaction);

        $stripePaymentIntent = $this->createPaymentIntent('requires_capture');

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->cancelPaymentIntentsMethodAction
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new StripePaymentIntentAction(
                    actionName: PaymentMethodInterface::CANCEL,
                    stripePaymentIntentConfig: $stripeAction->getStripeClientConfig(),
                    paymentTransaction: $cancelTransaction
                )
            )
            ->willThrowException($this->createMock(\Throwable::class));

        $this->expectException(\Throwable::class);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to re-authorize the payment transaction #{reAuthorizePaymentTransactionId}: '
                . 'failed to cancel the source payment transaction #{sourcePaymentTransactionId}',
                [
                    'reAuthorizePaymentTransactionId' => $reAuthorizeTransaction->getId(),
                    'sourcePaymentTransactionId' => $sourceTransaction->getId(),
                ]
            );

        $expectedTransaction = clone $authorizeTransaction;

        try {
            $this->executor->executeAction($stripeAction);
        } finally {
            self::assertEquals(
                $expectedTransaction
                    ->setAction(PaymentMethodInterface::AUTHORIZE)
                    ->setSuccessful(true)
                    ->setActive(true)
                    ->setReference($stripePaymentIntent->id)
                    ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, true)
                    ->addTransactionOption(
                        StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
                        $stripePaymentIntent->id
                    )
                    ->addTransactionOption(
                        StripePaymentIntentActionInterface::CUSTOMER_ID,
                        $stripePaymentIntent->customer
                    )
                    ->addTransactionOption(
                        StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                        $stripePaymentIntent->payment_method
                    ),
                $authorizeTransaction
            );
            self::assertTrue($reAuthorizeTransaction->isSuccessful());
            self::assertFalse($sourceTransaction->isActive());
        }
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionWhenEventDispatcherModifiesRequest(): void
    {
        $sourceTransaction = $this->createSourceTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::TRANSACTION_OPTIONS
        );
        $reAuthorizeTransaction = $this->createReAuthorizeTransaction($sourceTransaction);
        $cancelTransaction = $this->createCancelTransaction($sourceTransaction);
        $authorizeTransaction = $this->createAuthorizeTransaction($sourceTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(2))
            ->method('createPaymentTransactionByParentTransaction')
            ->withConsecutive(
                [PaymentMethodInterface::CANCEL, $sourceTransaction],
                [PaymentMethodInterface::AUTHORIZE, $sourceTransaction],
            )
            ->willReturn($cancelTransaction, $authorizeTransaction);

        $this->paymentTransactionProvider
            ->expects(self::exactly(4))
            ->method('savePaymentTransaction')
            ->withConsecutive(
                [$cancelTransaction],
                [$authorizeTransaction],
                [$authorizeTransaction],
                [$cancelTransaction]
            );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::RE_AUTHORIZE,
            $this->stripePaymentElementConfig,
            $reAuthorizeTransaction
        );

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $authorizeTransaction->getCurrency(),
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => $sourceTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID
                ),
                'customer' => $sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID),
                'metadata' => [
                    'payment_transaction_access_identifier' => $authorizeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $authorizeTransaction->getAccessToken(),
                ],
            ],
        ];

        $this->stripeClient
            ->expects(self::once())
            ->method('beginScopeFor')
            ->with($authorizeTransaction);

        $stripePaymentIntent = $this->createPaymentIntent('requires_capture');

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
                        self::assertEquals('paymentIntentsCreate', $beforeRequestEvent->getRequestName());
                        self::assertSame($requestArgs, $beforeRequestEvent->getRequestArgs());

                        $requestArgs[1]['metadata']['sample_key'] = 'sample_value';
                        $beforeRequestEvent->setRequestArgs($requestArgs);

                        $paymentIntentService
                            ->expects(self::once())
                            ->method('create')
                            ->with(...$beforeRequestEvent->getRequestArgs())
                            ->willReturn($stripePaymentIntent);

                        return true;
                    }
                )
            );

        $this->cancelPaymentIntentsMethodAction
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new StripePaymentIntentAction(
                    actionName: PaymentMethodInterface::CANCEL,
                    stripePaymentIntentConfig: $stripeAction->getStripeClientConfig(),
                    paymentTransaction: $cancelTransaction
                )
            )
            ->willReturn(
                new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent)
            );

        $expectedTransaction = clone $authorizeTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::AUTHORIZE)
                ->setSuccessful(true)
                ->setActive(true)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, true)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $stripePaymentIntent->customer)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentIntent->payment_method
                ),
            $authorizeTransaction
        );
        self::assertTrue($reAuthorizeTransaction->isSuccessful());
        self::assertFalse($sourceTransaction->isActive());
    }
}
