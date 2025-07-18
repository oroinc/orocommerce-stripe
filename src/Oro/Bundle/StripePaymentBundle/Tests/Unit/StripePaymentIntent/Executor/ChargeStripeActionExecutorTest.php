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
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor\StripeCustomerActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\ChargeStripeActionExecutor;
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
 */
final class ChargeStripeActionExecutorTest extends TestCase
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

    private ChargeStripeActionExecutor $executor;

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

        $this->executor = new ChargeStripeActionExecutor(
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

    private function createPaymentTransaction(
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

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::PURCHASE));
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::CHARGE));
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
            $this->createPaymentTransaction(PaymentMethodInterface::AUTHORIZE, self::CONFIRMATION_TOKEN)
        );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenConfirmationTokenMissing(): void
    {
        $purchaseTransaction = $this->createPaymentTransaction(PaymentMethodInterface::PURCHASE, []);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
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
                'Cannot charge the payment transaction #{paymentTransactionId}: confirmationToken data is missing',
                [
                    'paymentTransactionId' => $purchaseTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }


    /**
     * @dataProvider invalidConfirmationTokenProvider
     */
    public function testIsApplicableForActionReturnsFalseWhenConfirmationTokenInvalid(array $confirmationToken): void
    {
        $purchaseTransaction = $this->createPaymentTransaction(PaymentMethodInterface::PURCHASE, $confirmationToken);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
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
                'Cannot charge the payment transaction #{paymentTransactionId}: confirmationToken data is missing',
                [
                    'paymentTransactionId' => $purchaseTransaction->getId(),
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

    public function testIsApplicableForActionReturnsTrueWhenAutomaticCaptureMethod(): void
    {
        $purchaseTransaction = $this->createPaymentTransaction(
            PaymentMethodInterface::PURCHASE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getCaptureMethod')
            ->willReturn('automatic');

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getPaymentMethodTypesWithManualCapture');

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenCharge(): void
    {
        $chargeTransaction = $this->createPaymentTransaction(
            PaymentMethodInterface::CHARGE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $chargeTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getCaptureMethod');

        $this->stripePaymentElementConfig
            ->expects(self::never())
            ->method('getPaymentMethodTypesWithManualCapture');

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenPurchaseAndPaymentMethodTypeWithManualCapture(): void
    {
        $purchaseTransaction = $this->createPaymentTransaction(
            PaymentMethodInterface::PURCHASE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getCaptureMethod')
            ->willReturn('manual');

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodTypesWithManualCapture')
            ->willReturn([self::CONFIRMATION_TOKEN['paymentMethodPreview']['type']]);

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenPurchaseAndPaymentMethodTypeWithoutManualCapture(): void
    {
        $confirmationToken = [
            'id' => 'con_123',
            'paymentMethodPreview' => [
                'type' => 'us_bank_account',
            ],
        ];
        $purchaseTransaction = $this->createPaymentTransaction(
            PaymentMethodInterface::PURCHASE,
            $confirmationToken
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $purchaseTransaction
        );

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getCaptureMethod')
            ->willReturn('manual');

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodTypesWithManualCapture')
            ->willReturn(['card']);

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    /**
     * @dataProvider successfulPaymentIntentProvider
     */
    public function testExecuteActionSuccessfullyCreatesPaymentIntent(
        StripePaymentIntent $stripePaymentIntent,
        bool $successful,
        bool $requiresAction
    ): void {
        $chargeTransaction = $this->createPaymentTransaction(
            PaymentMethodInterface::CHARGE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $chargeTransaction
        );

        $stripeCustomer = new StripeCustomer(self::STRIPE_CUSTOMER_ID);
        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new FindOrCreateStripeCustomerAction(
                    stripeClientConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $chargeTransaction
                )
            )
            ->willReturn(new StripeCustomerActionResult(true, $stripeCustomer));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $chargeTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => self::CONFIRMATION_TOKEN['id'],
                'metadata' => [
                    'payment_transaction_access_identifier' => $chargeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $chargeTransaction->getAccessToken(),
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

        $expectedTransaction = clone $chargeTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: $successful, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CHARGE)
                ->setSuccessful($successful)
                ->setActive($requiresAction)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $stripePaymentIntent->customer)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentIntent->payment_method
                ),
            $chargeTransaction
        );
    }

    public function successfulPaymentIntentProvider(): \Generator
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'succeeded';
        $paymentIntent->client_secret = 'pi_123_secret_123';
        $paymentIntent->payment_method = 'pm_123';
        $paymentIntent->customer = self::STRIPE_CUSTOMER_ID;

        yield 'requires_capture' => [$paymentIntent, true, false];

        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'processing';
        $paymentIntent->client_secret = 'pi_123_secret_123';
        $paymentIntent->payment_method = 'pm_123';
        $paymentIntent->customer = self::STRIPE_CUSTOMER_ID;

        yield 'processing' => [$paymentIntent, true, false];

        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'requires_action';
        $paymentIntent->client_secret = 'pi_123_secret_123';
        $paymentIntent->payment_method = 'pm_123';
        $paymentIntent->customer = self::STRIPE_CUSTOMER_ID;

        yield 'requires_action' => [$paymentIntent, false, true];
    }

    /**
     * @dataProvider successfulPaymentIntentProvider
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionSuccessfullyCreatesPaymentIntentWhenNoCustomerId(
        StripePaymentIntent $paymentIntent,
        bool $successful,
        bool $requiresAction
    ): void {
        unset($paymentIntent->customer);

        $chargeTransaction = $this->createPaymentTransaction(
            PaymentMethodInterface::CHARGE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $chargeTransaction
        );

        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new FindOrCreateStripeCustomerAction(
                    stripeClientConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $chargeTransaction
                )
            )
            ->willReturn(new StripeCustomerActionResult(false));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $chargeTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => self::CONFIRMATION_TOKEN['id'],
                'metadata' => [
                    'payment_transaction_access_identifier' => $chargeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $chargeTransaction->getAccessToken(),
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
            ->willReturn($paymentIntent);

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot find or create a Stripe customer for the payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $chargeTransaction->getId(),
                ]
            );

        $expectedTransaction = clone $chargeTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: $successful, stripePaymentIntent: $paymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CHARGE)
                ->setSuccessful($successful)
                ->setActive($requiresAction)
                ->setReference($paymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $paymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, '')
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $paymentIntent->payment_method
                ),
            $chargeTransaction
        );
    }

    /**
     * @dataProvider failedPaymentIntentProvider
     */
    public function testExecuteActionHandlesFailedPaymentIntentCreation(StripePaymentIntent $stripePaymentIntent): void
    {
        $chargeTransaction = $this->createPaymentTransaction(
            PaymentMethodInterface::CHARGE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $chargeTransaction
        );

        $stripeCustomer = new StripeCustomer(self::STRIPE_CUSTOMER_ID);
        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new FindOrCreateStripeCustomerAction(
                    stripeClientConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $chargeTransaction
                )
            )
            ->willReturn(new StripeCustomerActionResult(true, $stripeCustomer));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $chargeTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => self::CONFIRMATION_TOKEN['id'],
                'metadata' => [
                    'payment_transaction_access_identifier' => $chargeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $chargeTransaction->getAccessToken(),
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

        $expectedTransaction = clone $chargeTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CHARGE)
                ->setSuccessful(false)
                ->setActive(false)
                ->setReference($stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $stripePaymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, '')
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_METHOD_ID, ''),
            $chargeTransaction
        );
    }

    public function failedPaymentIntentProvider(): \Generator
    {
        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'requires_payment_method';

        yield 'requires_payment_method' => [$paymentIntent];

        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'canceled';

        yield 'canceled' => [$paymentIntent];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionWhenEventDispatcherModifiesRequest(): void
    {
        $chargeTransaction = $this->createPaymentTransaction(
            PaymentMethodInterface::PURCHASE,
            self::CONFIRMATION_TOKEN
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $chargeTransaction
        );

        $stripeCustomer = new StripeCustomer(self::STRIPE_CUSTOMER_ID);
        $this->stripeCustomerActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new FindOrCreateStripeCustomerAction(
                    stripeClientConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $chargeTransaction
                )
            )
            ->willReturn(new StripeCustomerActionResult(true, $stripeCustomer));

        $requestArgs = [
            [
                'amount' => 12345,
                'currency' => $chargeTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'confirm' => true,
                'return_url' => self::RETURN_URL,
                'confirmation_token' => self::CONFIRMATION_TOKEN['id'],
                'metadata' => [
                    'payment_transaction_access_identifier' => $chargeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $chargeTransaction->getAccessToken(),
                ],
                'customer' => $stripeCustomer->id,
            ],
        ];

        $paymentIntent = new StripePaymentIntent('pi_123');
        $paymentIntent->status = 'succeeded';
        $paymentIntent->client_secret = 'pi_123_secret_123';
        $paymentIntent->payment_method = 'pm_123';
        $paymentIntent->customer = self::STRIPE_CUSTOMER_ID;

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
                        self::assertEquals('paymentIntentsCreate', $beforeRequestEvent->getRequestName());
                        self::assertSame($requestArgs, $beforeRequestEvent->getRequestArgs());

                        $requestArgs[0]['metadata']['sample_key'] = 'sample_value';
                        $beforeRequestEvent->setRequestArgs($requestArgs);

                        $paymentIntentService
                            ->expects(self::once())
                            ->method('create')
                            ->with(...$beforeRequestEvent->getRequestArgs())
                            ->willReturn($paymentIntent);

                        return true;
                    }
                )
            );

        $this->assertLoggerNotCalled();

        $expectedTransaction = clone $chargeTransaction;
        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $paymentIntent),
            $result
        );
        self::assertEquals(
            $expectedTransaction
                ->setAction(PaymentMethodInterface::CHARGE)
                ->setSuccessful(true)
                ->setActive(false)
                ->setReference($paymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID, $paymentIntent->id)
                ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $paymentIntent->customer)
                ->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $paymentIntent->payment_method
                ),
            $chargeTransaction
        );
    }
}
