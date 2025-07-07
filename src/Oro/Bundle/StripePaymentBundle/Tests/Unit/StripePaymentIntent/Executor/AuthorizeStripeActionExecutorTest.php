<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\GenericStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor\StripeCustomerActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\AuthorizeStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentIntent as StripePaymentIntent;
use Stripe\Service\PaymentIntentService as StripePaymentIntentService;
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
    private const string SAMPLE_ACCESS_IDENTIFIER = 'sample_identifier';
    private const array CONFIRMATION_TOKEN = [
        'id' => 'con_123',
        'paymentMethodPreview' => [
            'type' => 'card',
        ],
    ];

    private AuthorizeStripeActionExecutor $executor;

    private MockObject&StripeCustomerActionExecutorInterface $stripeCustomerActionExecutor;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private MockObject&LoggingStripeClient $stripeClient;

    protected function setUp(): void
    {
        $stripeClientFactory = $this->createMock(StripeClientFactoryInterface::class);
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

        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripeClient = $this->createMock(LoggingStripeClient::class);
        $stripeClientFactory
            ->method('createStripeClient')
            ->with($this->stripePaymentElementConfig)
            ->willReturn($this->stripeClient);

        $urlGenerator
            ->method('generate')
            ->with(
                'oro_payment_callback_return',
                ['accessIdentifier' => self::SAMPLE_ACCESS_IDENTIFIER],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn(self::RETURN_URL);
    }

    private function createAuthorizeTransaction(
        string $action,
        array $confirmationToken
    ): PaymentTransaction {
        $authorizeTransaction = new PaymentTransaction();
        ReflectionUtil::setId($authorizeTransaction, 123);
        $authorizeTransaction->setAction($action);
        $authorizeTransaction->setAccessIdentifier(self::SAMPLE_ACCESS_IDENTIFIER);
        $authorizeTransaction->setAccessToken('sample_token');
        $authorizeTransaction->setAmount(123.45);
        $authorizeTransaction->setCurrency('USD');
        $authorizeTransaction->addTransactionOption('additionalData', ['confirmationToken' => $confirmationToken]);

        return $authorizeTransaction;
    }

    private function createStripePaymentIntent(string $status): StripePaymentIntent
    {
        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = $status;
        $stripePaymentIntent->client_secret = 'pi_123_secret_123';
        $stripePaymentIntent->payment_method = 'pm_123';
        $stripePaymentIntent->customer = self::STRIPE_CUSTOMER_ID;

        return $stripePaymentIntent;
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::PURCHASE));
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::AUTHORIZE));
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotSupportedAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::CHARGE));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedActionName(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $this->createAuthorizeTransaction(PaymentMethodInterface::CHARGE, self::CONFIRMATION_TOKEN)
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenPurchaseAndNotManualCaptureMethod(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $this->createAuthorizeTransaction(PaymentMethodInterface::PURCHASE, self::CONFIRMATION_TOKEN)
        );

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getCaptureMethod')
            ->willReturn('automatic');

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

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getCaptureMethod');

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getPaymentMethodTypesWithManualCapture');

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

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getCaptureMethod')
            ->willReturn('manual');

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getPaymentMethodTypesWithManualCapture');

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

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getCaptureMethod')
            ->willReturn('manual');

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getPaymentMethodTypesWithManualCapture');

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

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getCaptureMethod');

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getPaymentMethodTypesWithManualCapture');

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
        yield 'no id' => [['paymentMethodPreview' => ['type' => 'cards']]];
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

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getCaptureMethod')
            ->willReturn('manual');

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodTypesWithManualCapture')
            ->willReturn(['card']);

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
        $paymentTransaction = $this->createAuthorizeTransaction(PaymentMethodInterface::AUTHORIZE, $confirmationToken);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getCaptureMethod');

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodTypesWithManualCapture')
            ->willReturn(['card']);

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenPurchase(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::PURCHASE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getCaptureMethod')
            ->willReturn('manual');

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodTypesWithManualCapture')
            ->willReturn([self::CONFIRMATION_TOKEN['paymentMethodPreview']['type']]);

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenAuthorize(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getCaptureMethod');

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodTypesWithManualCapture')
            ->willReturn([self::CONFIRMATION_TOKEN['paymentMethodPreview']['type']]);

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    /**
     * @dataProvider successfulPaymentIntentProvider
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionSuccessfullyCreatesPaymentIntent(
        StripePaymentIntent $stripePaymentIntent,
        bool $successful
    ): void {
        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::CONFIRMATION_TOKEN
        );

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

        $this->stripePaymentElementConfig
            ->expects(self::atLeastOnce())
            ->method('isReAuthorizationEnabled')
            ->willReturn(false);

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $paymentTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    self::CONFIRMATION_TOKEN['paymentMethodPreview']['type'] => [
                        'capture_method' => 'manual',
                    ],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => self::CONFIRMATION_TOKEN['id'],
                'metadata' => [
                    'payment_transaction_access_identifier' => $paymentTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $paymentTransaction->getAccessToken(),
                ],
                'customer' => $stripeCustomer->id,
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

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedPaymentTransaction = clone $paymentTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: $successful, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedPaymentTransaction
                ->setAction(PaymentMethodInterface::AUTHORIZE)
                ->setSuccessful($successful)
                ->setActive(true)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, false)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $stripePaymentIntent->customer)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentIntent->payment_method
                ),
            $paymentTransaction
        );
    }

    public function successfulPaymentIntentProvider(): \Generator
    {
        $stripePaymentIntent = $this->createStripePaymentIntent('requires_capture');

        yield 'requires_capture' => [$stripePaymentIntent, true];

        $stripePaymentIntent = $this->createStripePaymentIntent('requires_action');

        yield 'requires_action' => [$stripePaymentIntent, false];
    }

    /**
     * @dataProvider successfulPaymentIntentProvider
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionSuccessfullyCreatesPaymentIntentWhenReAuthorizationEnabled(
        StripePaymentIntent $stripePaymentIntent,
        bool $successful
    ): void {
        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::CONFIRMATION_TOKEN
        );

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

        $this->stripePaymentElementConfig
            ->expects(self::atLeastOnce())
            ->method('isReAuthorizationEnabled')
            ->willReturn(true);

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $paymentTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    self::CONFIRMATION_TOKEN['paymentMethodPreview']['type'] => [
                        'capture_method' => 'manual',
                    ],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => self::CONFIRMATION_TOKEN['id'],
                'metadata' => [
                    'payment_transaction_access_identifier' => $paymentTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $paymentTransaction->getAccessToken(),
                ],
                'setup_future_usage' => 'off_session',
                'customer' => $stripeCustomer->id,
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

        $stripePaymentIntent->setup_future_usage = 'off_session';

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedPaymentTransaction = clone $paymentTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: $successful, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedPaymentTransaction
                ->setAction(PaymentMethodInterface::AUTHORIZE)
                ->setSuccessful($successful)
                ->setActive(true)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, true)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $stripePaymentIntent->customer)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentIntent->payment_method
                ),
            $paymentTransaction
        );
    }

    /**
     * @dataProvider successfulPaymentIntentProvider
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionSuccessfullyCreatesPaymentIntentWhenNoStripeCustomerId(
        StripePaymentIntent $stripePaymentIntent,
        bool $successful
    ): void {
        unset($stripePaymentIntent->customer);

        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new FindOrCreateStripeCustomerAction(
                    stripeClientConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $paymentTransaction
                )
            )
            ->willReturn(new StripeCustomerActionResult(false));

        $this->stripePaymentElementConfig
            ->expects(self::atLeastOnce())
            ->method('isReAuthorizationEnabled')
            ->willReturn(false);

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $paymentTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    self::CONFIRMATION_TOKEN['paymentMethodPreview']['type'] => [
                        'capture_method' => 'manual',
                    ],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => self::CONFIRMATION_TOKEN['id'],
                'metadata' => [
                    'payment_transaction_access_identifier' => $paymentTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $paymentTransaction->getAccessToken(),
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

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot find or create a Stripe customer for the payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                ]
            );

        $expectedPaymentTransaction = clone $paymentTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: $successful, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedPaymentTransaction
                ->setAction(PaymentMethodInterface::AUTHORIZE)
                ->setSuccessful($successful)
                ->setActive(true)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, false)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, '')
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentIntent->payment_method
                ),
            $paymentTransaction
        );
    }

    /**
     * @dataProvider failedPaymentIntentProvider
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionHandlesFailedPaymentIntentCreation(StripePaymentIntent $stripePaymentIntent): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::CONFIRMATION_TOKEN
        );

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

        $this->stripePaymentElementConfig
            ->expects(self::atLeastOnce())
            ->method('isReAuthorizationEnabled')
            ->willReturn(false);

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $paymentTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    self::CONFIRMATION_TOKEN['paymentMethodPreview']['type'] => [
                        'capture_method' => 'manual',
                    ],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => self::CONFIRMATION_TOKEN['id'],
                'metadata' => [
                    'payment_transaction_access_identifier' => $paymentTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $paymentTransaction->getAccessToken(),
                ],
                'customer' => $stripeCustomer->id,
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

        $paymentIntentService = $this->createMock(StripePaymentIntentService::class);
        $this->stripeClient->paymentIntents = $paymentIntentService;
        $paymentIntentService
            ->expects(self::once())
            ->method('create')
            ->with(...$requestArgs)
            ->willReturn($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $expectedPaymentTransaction = clone $paymentTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedPaymentTransaction
                ->setAction(PaymentMethodInterface::AUTHORIZE)
                ->setSuccessful(false)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, false)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, '')
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_METHOD_ID, ''),
            $paymentTransaction
        );
    }

    public function failedPaymentIntentProvider(): \Generator
    {
        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = 'requires_payment_method';

        yield 'requires_payment_method' => [$stripePaymentIntent];

        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = 'canceled';

        yield 'canceled' => [$stripePaymentIntent];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionWhenEventDispatcherModifiesRequest(): void
    {
        $paymentTransaction = $this->createAuthorizeTransaction(
            PaymentMethodInterface::AUTHORIZE,
            self::CONFIRMATION_TOKEN
        );

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

        $this->stripePaymentElementConfig
            ->expects(self::atLeastOnce())
            ->method('isReAuthorizationEnabled')
            ->willReturn(false);

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $paymentTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    self::CONFIRMATION_TOKEN['paymentMethodPreview']['type'] => [
                        'capture_method' => 'manual',
                    ],
                ],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => self::CONFIRMATION_TOKEN['id'],
                'metadata' => [
                    'payment_transaction_access_identifier' => $paymentTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $paymentTransaction->getAccessToken(),
                ],
                'customer' => $stripeCustomer->id,
            ],
        ];

        $stripePaymentIntent = $this->createStripePaymentIntent('requires_capture');

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

                        $requestArgs[0]['metadata']['sample_key'] = 'sample_value';
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

        $this->assertLoggerNotCalled();

        $expectedPaymentTransaction = clone $paymentTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedPaymentTransaction
                ->setAction(PaymentMethodInterface::AUTHORIZE)
                ->setSuccessful(true)
                ->setActive(true)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, false)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $stripePaymentIntent->customer)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentIntent->payment_method
                ),
            $paymentTransaction
        );
    }
}
