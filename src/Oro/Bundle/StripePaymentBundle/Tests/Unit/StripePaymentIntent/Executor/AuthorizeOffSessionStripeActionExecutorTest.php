<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\GenericStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor\StripeCustomerActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\AuthorizeOffSessionStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\Test\StripeClient\MockingStripeClient;
use Oro\Bundle\StripePaymentBundle\Test\StripeClient\MockingStripeClientFactory;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class AuthorizeOffSessionStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const string STRIPE_CUSTOMER_ID = 'cus_123';
    private const string STRIPE_PAYMENT_METHOD_ID = 'pm_123';
    private const string SAMPLE_ACCESS_IDENTIFIER = 'sample_identifier';
    private const string SAMPLE_ACCESS_TOKEN = 'sample_token';

    private AuthorizeOffSessionStripeActionExecutor $executor;

    private MockObject&StripeCustomerActionExecutorInterface $stripeCustomerActionExecutor;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private StripePaymentElementConfig $stripePaymentElementConfig;

    private MockingStripeClient $stripeClient;

    #[\Override]
    protected function setUp(): void
    {
        $stripeClientFactory = new MockingStripeClientFactory();
        $this->stripeCustomerActionExecutor = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new AuthorizeOffSessionStripeActionExecutor(
            $stripeClientFactory,
            $this->stripeCustomerActionExecutor,
            new GenericStripeAmountConverter(),
            $this->eventDispatcher
        );

        $this->setUpLoggerMock($this->executor);

        $this->stripePaymentElementConfig = $this->createStripePaymentElementConfig();
        $this->stripeClient = MockingStripeClient::instance();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->stripeClient->reset();
    }

    private function createStripePaymentElementConfig(): StripePaymentElementConfig
    {
        return new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'manual',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => ['card'],
            StripePaymentElementConfig::RE_AUTHORIZATION_ENABLED => true,
        ]);
    }

    private function createAuthorizeTransaction(
        string $action = PaymentMethodInterface::AUTHORIZE,
        bool $offSession = true,
        ?string $stripePaymentMethodId = self::STRIPE_PAYMENT_METHOD_ID,
        ?string $stripeCustomerId = null
    ): PaymentTransaction {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);
        $paymentTransaction->setAction($action);
        $paymentTransaction->setAccessIdentifier(self::SAMPLE_ACCESS_IDENTIFIER);
        $paymentTransaction->setAccessToken(self::SAMPLE_ACCESS_TOKEN);
        $paymentTransaction->setAmount(123.45);
        $paymentTransaction->setCurrency('USD');

        if ($offSession) {
            $paymentTransaction->addTransactionOption(StripePaymentIntentActionInterface::OFF_SESSION, true);
        }

        if ($stripePaymentMethodId) {
            $paymentTransaction->addTransactionOption(
                StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                $stripePaymentMethodId
            );
        }

        if ($stripeCustomerId) {
            $paymentTransaction->addTransactionOption(
                StripePaymentIntentActionInterface::CUSTOMER_ID,
                $stripeCustomerId
            );
        }

        return $paymentTransaction;
    }

    private function createStripePaymentIntent(
        string $status,
        ?string $setupFutureUsage = null
    ): StripePaymentIntent {
        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = $status;
        $stripePaymentIntent->payment_method = self::STRIPE_PAYMENT_METHOD_ID;
        $stripePaymentIntent->customer = self::STRIPE_CUSTOMER_ID;

        if ($setupFutureUsage !== null) {
            $stripePaymentIntent->setup_future_usage = $setupFutureUsage;
        }

        return $stripePaymentIntent;
    }

    public function testIsSupportedByActionNameReturnsTrueWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::PURCHASE));
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::AUTHORIZE));
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotSupportedAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::CHARGE));
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::CAPTURE));
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::REFUND));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedActionName(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $this->createAuthorizeTransaction(PaymentMethodInterface::CHARGE)
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenOffSessionFlagMissing(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::AUTHORIZE,
            offSession: false
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenPaymentMethodIdMissing(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::AUTHORIZE,
            offSession: true,
            stripePaymentMethodId: null
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('info')
            ->with(
                'Skipping "authorize" action: no payment method id found in the options '
                . 'of the payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenPurchaseAndNotManualCaptureMethod(): void
    {
        $stripePaymentIntentConfig = new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'automatic',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => [],
        ]);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $stripePaymentIntentConfig,
            $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE)
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenAuthorize(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $this->createAuthorizeTransaction(PaymentMethodInterface::AUTHORIZE)
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenPurchaseWithManualCaptureMethod(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE)
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionSuccessfullyCreatesPaymentIntentWhenRequiresCapture(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $stripeCustomer = new StripeCustomer(self::STRIPE_CUSTOMER_ID);
        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new FindOrCreateStripeCustomerAction(
                    stripeClientConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $paymentTransaction
                )
            )
            ->willReturn(new StripeCustomerActionResult(true, $stripeCustomer));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => 'USD',
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                'customer' => self::STRIPE_CUSTOMER_ID,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(
            status: 'requires_capture',
            setupFutureUsage: 'off_session'
        );
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );

        self::assertEquals(PaymentMethodInterface::AUTHORIZE, $paymentTransaction->getAction());
        self::assertTrue($paymentTransaction->isSuccessful());
        self::assertTrue($paymentTransaction->isActive());
        self::assertEquals('pi_123', $paymentTransaction->getReference());
        self::assertTrue(
            $paymentTransaction->getTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED)
        );
        self::assertEquals(
            'pi_123',
            $paymentTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID)
        );
        self::assertEquals(
            self::STRIPE_PAYMENT_METHOD_ID,
            $paymentTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_METHOD_ID)
        );
        self::assertEquals(
            self::STRIPE_CUSTOMER_ID,
            $paymentTransaction->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID)
        );
    }

    public function testExecuteActionSuccessfullyCreatesPaymentIntentWhenRequiresAction(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $stripeCustomer = new StripeCustomer(self::STRIPE_CUSTOMER_ID);
        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturn(new StripeCustomerActionResult(true, $stripeCustomer));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => 'USD',
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                'customer' => self::STRIPE_CUSTOMER_ID,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_action');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent),
            $result
        );

        self::assertEquals(PaymentMethodInterface::AUTHORIZE, $paymentTransaction->getAction());
        self::assertFalse($paymentTransaction->isSuccessful());
        self::assertTrue($paymentTransaction->isActive());
        self::assertEquals('pi_123', $paymentTransaction->getReference());
        self::assertTrue(
            $paymentTransaction->getTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED)
        );
        self::assertEquals(
            'pi_123',
            $paymentTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID)
        );
    }

    public function testExecuteActionCreatesPaymentIntentWithoutCustomerWhenCustomerIdProvided(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(
            stripeCustomerId: self::STRIPE_CUSTOMER_ID
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        // Customer executor should not be called because customer ID is already in transaction
        $this->stripeCustomerActionExecutor
            ->expects(self::never())
            ->method('executeAction');

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => 'USD',
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                'customer' => self::STRIPE_CUSTOMER_ID,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_capture');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
    }

    public function testExecuteActionCreatesPaymentIntentWithoutCustomerWhenCustomerActionFails(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturn(new StripeCustomerActionResult(false, null));

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot find or create a Stripe customer for the payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                ]
            );

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => 'USD',
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCreate',
            $requestArgs
        );

        // Verify that customer is NOT included in request args
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_capture');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
    }

    public function testExecuteActionHandlesEventDispatcherModifyingRequestArgs(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $stripeCustomer = new StripeCustomer(self::STRIPE_CUSTOMER_ID);
        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturn(new StripeCustomerActionResult(true, $stripeCustomer));

        // Modify request args in event dispatcher
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (StripePaymentIntentActionBeforeRequestEvent $event) {
                $requestArgs = $event->getRequestArgs();
                $requestArgs[0]['description'] = 'Modified by event';
                $event->setRequestArgs($requestArgs);

                return $event;
            });

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_capture');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());

        // Check that modified request args were used in the Stripe API request.
        $requestLogs = MockingStripeClient::instance()->getRequestLogs();
        self::assertNotEmpty($requestLogs);
        $lastRequest = end($requestLogs);
        self::assertEquals('Modified by event', $lastRequest['params']['description']);
    }

    public function testExecuteActionHandlesOtherPaymentIntentStatuses(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturn(new StripeCustomerActionResult(true, new StripeCustomer(self::STRIPE_CUSTOMER_ID)));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => 'USD',
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                'customer' => self::STRIPE_CUSTOMER_ID,
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

        // Create payment intent with a different status
        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'processing');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertFalse($result->isSuccessful());
        self::assertEquals(PaymentMethodInterface::AUTHORIZE, $paymentTransaction->getAction());
        self::assertFalse($paymentTransaction->isSuccessful());
        self::assertFalse($paymentTransaction->isActive());
        self::assertEquals('pi_123', $paymentTransaction->getReference());
    }

    public function testExecuteActionWithPurchaseAction(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturn(new StripeCustomerActionResult(true, new StripeCustomer(self::STRIPE_CUSTOMER_ID)));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => 'USD',
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                'customer' => self::STRIPE_CUSTOMER_ID,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(
            status: 'requires_capture',
            setupFutureUsage: 'off_session'
        );
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        self::assertEquals(PaymentMethodInterface::AUTHORIZE, $paymentTransaction->getAction());
        self::assertTrue($paymentTransaction->isSuccessful());
    }

    public function testExecuteActionSetsReAuthorizationEnabledWhenConfigEnabled(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturn(new StripeCustomerActionResult(true, new StripeCustomer(self::STRIPE_CUSTOMER_ID)));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => 'USD',
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                'customer' => self::STRIPE_CUSTOMER_ID,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_capture');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        self::assertTrue(
            $paymentTransaction->getTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED)
        );
    }

    public function testExecuteActionSetsReAuthorizationDisabledWhenConfigDisabled(): void
    {
        // Create a config with RE_AUTHORIZATION_ENABLED set to false
        $configWithoutReauth = new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'manual',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => ['card'],
            StripePaymentElementConfig::RE_AUTHORIZATION_ENABLED => false,
        ]);

        $paymentTransaction = $this->createAuthorizeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $configWithoutReauth,
            $paymentTransaction
        );

        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturn(new StripeCustomerActionResult(true, new StripeCustomer(self::STRIPE_CUSTOMER_ID)));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => 'USD',
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                'customer' => self::STRIPE_CUSTOMER_ID,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_capture');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        self::assertFalse(
            $paymentTransaction->getTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED)
        );
    }
}
