<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\GenericStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor\StripeCustomerActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\ChargeOffSessionStripeActionExecutor;
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
final class ChargeOffSessionStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const string STRIPE_CUSTOMER_ID = 'cus_123';
    private const string STRIPE_PAYMENT_METHOD_ID = 'pm_123';
    private const string SAMPLE_ACCESS_IDENTIFIER = 'sample_identifier';
    private const string SAMPLE_ACCESS_TOKEN = 'sample_token';

    private ChargeOffSessionStripeActionExecutor $executor;

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

        $this->executor = new ChargeOffSessionStripeActionExecutor(
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
            StripePaymentElementConfig::CAPTURE_METHOD => 'automatic',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => [],
        ]);
    }

    private function createChargeTransaction(
        string $action = PaymentMethodInterface::CHARGE,
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

    private function createStripePaymentIntent(string $status): StripePaymentIntent
    {
        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = $status;
        $stripePaymentIntent->payment_method = self::STRIPE_PAYMENT_METHOD_ID;
        $stripePaymentIntent->customer = self::STRIPE_CUSTOMER_ID;

        return $stripePaymentIntent;
    }

    public function testIsSupportedByActionNameReturnsTrueWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::PURCHASE));
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::CHARGE));
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotSupportedAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::AUTHORIZE));
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::CAPTURE));
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::REFUND));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedActionName(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $this->createChargeTransaction(PaymentMethodInterface::AUTHORIZE)
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenOffSessionFlagMissing(): void
    {
        $paymentTransaction = $this->createChargeTransaction(
            PaymentMethodInterface::CHARGE,
            offSession: false
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenPaymentMethodIdMissing(): void
    {
        $paymentTransaction = $this->createChargeTransaction(
            PaymentMethodInterface::CHARGE,
            offSession: true,
            stripePaymentMethodId: null
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('info')
            ->with(
                'Skipping "charge" action: no payment method id found in the options '
                . 'of the payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                ]
            );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenPurchaseAndNotAutomaticCaptureMethod(): void
    {
        $stripePaymentIntentConfig = new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'manual',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => ['card'],
        ]);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $stripePaymentIntentConfig,
            $this->createChargeTransaction(PaymentMethodInterface::PURCHASE)
        );

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenCharge(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $this->createChargeTransaction(PaymentMethodInterface::CHARGE)
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenPurchaseWithAutomaticCaptureMethod(): void
    {
        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $this->createChargeTransaction(PaymentMethodInterface::PURCHASE)
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionSuccessfullyCreatesPaymentIntentWhenSucceeded(): void
    {
        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'succeeded');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );

        self::assertEquals(PaymentMethodInterface::CHARGE, $paymentTransaction->getAction());
        self::assertTrue($paymentTransaction->isSuccessful());
        self::assertFalse($paymentTransaction->isActive());
        self::assertEquals('pi_123', $paymentTransaction->getReference());
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

    public function testExecuteActionSuccessfullyCreatesPaymentIntentWhenProcessing(): void
    {
        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'processing');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent),
            $result
        );

        self::assertEquals(PaymentMethodInterface::CHARGE, $paymentTransaction->getAction());
        self::assertTrue($paymentTransaction->isSuccessful());
        self::assertFalse($paymentTransaction->isActive());
        self::assertEquals('pi_123', $paymentTransaction->getReference());
    }

    public function testExecuteActionCreatesPaymentIntentWhenRequiresAction(): void
    {
        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
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

        self::assertEquals(PaymentMethodInterface::CHARGE, $paymentTransaction->getAction());
        self::assertFalse($paymentTransaction->isSuccessful());
        self::assertTrue($paymentTransaction->isActive());
        self::assertEquals('pi_123', $paymentTransaction->getReference());
        self::assertEquals(
            'pi_123',
            $paymentTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID)
        );
    }

    public function testExecuteActionCreatesPaymentIntentWhenRequiresPaymentMethod(): void
    {
        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_payment_method');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripePaymentIntentActionResult(successful: false, stripePaymentIntent: $stripePaymentIntent),
            $result
        );

        self::assertEquals(PaymentMethodInterface::CHARGE, $paymentTransaction->getAction());
        self::assertFalse($paymentTransaction->isSuccessful());
        self::assertFalse($paymentTransaction->isActive());
        self::assertEquals('pi_123', $paymentTransaction->getReference());
    }

    public function testExecuteActionCreatesPaymentIntentWithoutCustomerWhenCustomerIdProvided(): void
    {
        $paymentTransaction = $this->createChargeTransaction(
            stripeCustomerId: self::STRIPE_CUSTOMER_ID
        );

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
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
                'capture_method' => 'automatic',
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'succeeded');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
    }

    public function testExecuteActionCreatesPaymentIntentWithoutCustomerWhenCustomerActionFails(): void
    {
        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
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
                'confirm' => true,
                'off_session' => true,
                'payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
                'metadata' => [
                    'payment_transaction_access_identifier' => self::SAMPLE_ACCESS_IDENTIFIER,
                    'payment_transaction_access_token' => self::SAMPLE_ACCESS_TOKEN,
                ],
                // Note: no 'customer' key
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'succeeded');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
    }

    public function testExecuteActionForPurchaseAction(): void
    {
        $paymentTransaction = $this->createChargeTransaction(PaymentMethodInterface::PURCHASE);

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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'succeeded');
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        self::assertEquals(PaymentMethodInterface::CHARGE, $paymentTransaction->getAction());
        self::assertTrue($paymentTransaction->isSuccessful());
    }

    public function testExecuteActionWithEventModifyingRequestArgs(): void
    {
        $paymentTransaction = $this->createChargeTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
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

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'succeeded');
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
