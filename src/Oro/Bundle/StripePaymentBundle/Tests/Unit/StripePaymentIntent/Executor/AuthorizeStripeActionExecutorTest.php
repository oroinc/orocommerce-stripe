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
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\AuthorizeStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\Test\StripeClient\MockingStripeClient;
use Oro\Bundle\StripePaymentBundle\Test\StripeClient\MockingStripeClientFactory;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
final class AuthorizeStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const string RETURN_URL = 'https://example.com/return';
    private const string STRIPE_CUSTOMER_ID = 'cus_123';
    private const string STRIPE_PAYMENT_METHOD_ID = 'pm_123';
    private const string SAMPLE_ACCESS_IDENTIFIER = 'sample_identifier';
    private const string SAMPLE_ACCESS_TOKEN = 'sample_token';
    private const array CONFIRMATION_TOKEN = [
        'id' => 'con_123',
        'paymentMethodPreview' => [
            'type' => 'card',
        ],
    ];

    private AuthorizeStripeActionExecutor $executor;

    private MockObject&StripeCustomerActionExecutorInterface $stripeCustomerActionExecutor;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private StripePaymentElementConfig $stripePaymentElementConfig;

    private MockingStripeClient $stripeClient;

    #[\Override]
    protected function setUp(): void
    {
        $stripeClientFactory = new MockingStripeClientFactory();
        $this->stripeCustomerActionExecutor = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new AuthorizeStripeActionExecutor(
            $stripeClientFactory,
            $this->stripeCustomerActionExecutor,
            new GenericStripeAmountConverter(),
            $urlGenerator,
            $this->eventDispatcher
        );

        $this->setUpLoggerMock($this->executor);

        $this->stripePaymentElementConfig = $this->createStripePaymentElementConfig();
        $this->stripeClient = MockingStripeClient::instance();

        $urlGenerator
            ->method('generate')
            ->with(
                'oro_payment_callback_return',
                ['accessIdentifier' => self::SAMPLE_ACCESS_IDENTIFIER],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn(self::RETURN_URL);
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
            StripePaymentElementConfig::RE_AUTHORIZATION_ENABLED => false,
        ]);
    }

    private function createAuthorizeTransaction(
        string $action = PaymentMethodInterface::AUTHORIZE,
        array $confirmationToken = self::CONFIRMATION_TOKEN
    ): PaymentTransaction {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);
        $paymentTransaction->setAction($action);
        $paymentTransaction->setAccessIdentifier(self::SAMPLE_ACCESS_IDENTIFIER);
        $paymentTransaction->setAccessToken(self::SAMPLE_ACCESS_TOKEN);
        $paymentTransaction->setAmount(123.45);
        $paymentTransaction->setCurrency('USD');
        $paymentTransaction->addTransactionOption('additionalData', ['confirmationToken' => $confirmationToken]);

        return $paymentTransaction;
    }

    private function createStripePaymentIntent(
        string $status,
        ?string $setupFutureUsage = null
    ): StripePaymentIntent {
        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = $status;
        $stripePaymentIntent->client_secret = 'pi_123_secret_123';
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

    public function testIsApplicableForActionReturnsFalseWhenPurchaseAndNotManualCaptureMethod(): void
    {
        $config = new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'automatic',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => [],
        ]);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $config,
            $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE)
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenAuthorizeAndConfirmationTokenMissing(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::AUTHORIZE, []);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot authorize the payment transaction #{paymentTransactionId}: confirmationToken data is missing',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenPurchaseAndConfirmationTokenMissing(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE, []);

        $config = new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'manual',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => ['card'],
        ]);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $config,
            $paymentTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot authorize the payment transaction #{paymentTransactionId}: confirmationToken data is missing',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    /**
     * @dataProvider invalidConfirmationTokenProvider
     */
    public function testIsApplicableForActionReturnsFalseWhenPurchaseAndConfirmationTokenInvalid(
        array $confirmationToken
    ): void {
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE, $confirmationToken);

        $config = new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'manual',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => ['card'],
        ]);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $config,
            $paymentTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot authorize the payment transaction #{paymentTransactionId}: confirmationToken data is missing',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    /**
     * @dataProvider invalidConfirmationTokenProvider
     */
    public function testIsApplicableForActionReturnsFalseWhenAuthorizeAndConfirmationTokenInvalid(
        array $confirmationToken
    ): void {
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::AUTHORIZE, $confirmationToken);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot authorize the payment transaction #{paymentTransactionId}: confirmationToken data is missing',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    private function invalidConfirmationTokenProvider(): \Generator
    {
        yield 'empty' => [[]];
        yield 'no paymentMethodPreview' => [['id' => 'con_123']];
        yield 'no id' => [['paymentMethodPreview' => ['type' => 'card']]];
        yield 'no type' => [['id' => 'con_123', 'paymentMethodPreview' => []]];
    }

    public function testIsApplicableForActionReturnsFalseWhenPurchaseAndPaymentMethodTypeWithoutManualCapture(): void
    {
        $confirmationToken = [
            'id' => 'con_123',
            'paymentMethodPreview' => [
                'type' => 'us_bank_account',
            ],
        ];
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE, $confirmationToken);

        $config = new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'manual',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => ['card'],
        ]);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $config,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenAuthorizeAndPaymentMethodTypeWithoutManualCapture(): void
    {
        $confirmationToken = [
            'id' => 'con_123',
            'paymentMethodPreview' => [
                'type' => 'us_bank_account',
            ],
        ];
        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::AUTHORIZE,
            $confirmationToken
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenPurchase(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenAuthorize(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::AUTHORIZE);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
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
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    'card' => ['capture_method' => 'manual'],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => 'con_123',
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

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );

        self::assertEquals(PaymentMethodInterface::AUTHORIZE, $paymentTransaction->getAction());
        self::assertTrue($paymentTransaction->isSuccessful());
        self::assertTrue($paymentTransaction->isActive());
        self::assertEquals('pi_123', $paymentTransaction->getReference());
        self::assertFalse(
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

    public function testExecuteActionCreatesPaymentIntentWhenRequiresAction(): void
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
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    'card' => ['capture_method' => 'manual'],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => 'con_123',
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
    }

    public function testExecuteActionCreatesPaymentIntentWhenRequiresPaymentMethod(): void
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
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    'card' => ['capture_method' => 'manual'],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => 'con_123',
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_payment_method');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent),
            $result
        );

        self::assertEquals(PaymentMethodInterface::AUTHORIZE, $paymentTransaction->getAction());
        self::assertFalse($paymentTransaction->isSuccessful());
        self::assertFalse($paymentTransaction->isActive());
        self::assertEquals('pi_123', $paymentTransaction->getReference());
    }

    public function testExecuteActionWithSetupFutureUsageFromConfig(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction();

        $config = new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'manual',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => ['card'],
            StripePaymentElementConfig::RE_AUTHORIZATION_ENABLED => true,
        ]);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $config,
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
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    'card' => ['capture_method' => 'manual'],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => 'con_123',
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                'customer' => self::STRIPE_CUSTOMER_ID,
                'setup_future_usage' => 'off_session',
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
        self::assertTrue(
            $paymentTransaction->getTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED)
        );
    }

    public function testExecuteActionWithSetupFutureUsageFromTransaction(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction();
        $paymentTransaction->addTransactionOption(StripePaymentIntentActionInterface::SETUP_FUTURE_USAGE, true);

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
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    'card' => ['capture_method' => 'manual'],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => 'con_123',
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                'customer' => self::STRIPE_CUSTOMER_ID,
                'setup_future_usage' => 'off_session',
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
        self::assertTrue(
            $paymentTransaction->getTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED)
        );
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
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    'card' => ['capture_method' => 'manual'],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => 'con_123',
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                // No customer in request args since customer action failed
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

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
    }

    public function testExecuteActionForPurchaseAction(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
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
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    'card' => ['capture_method' => 'manual'],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => 'con_123',
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
        self::assertEquals(PaymentMethodInterface::AUTHORIZE, $paymentTransaction->getAction());
    }

    public function testExecuteActionWithEventModifyingRequestArgs(): void
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

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (StripePaymentIntentActionBeforeRequestEvent $event) {
                // Simulate event listener modifying request args
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
}
