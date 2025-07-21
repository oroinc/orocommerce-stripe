<?php

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\PaymentMethod\StripePaymentElement;

use Oro\Bundle\PaymentBundle\Context\PaymentContext;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\StripePaymentElementMethod;
use Oro\Bundle\StripePaymentBundle\StripeAmountValidator\StripeAmountValidatorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEvent;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class StripePaymentElementMethodTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private StripePaymentElementMethod $paymentMethod;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private MockObject&StripePaymentIntentActionExecutorInterface $stripePaymentActionExecutor;

    private MockObject&StripeAmountValidatorInterface $stripeAmountValidator;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripePaymentActionExecutor = $this->createMock(StripePaymentIntentActionExecutorInterface::class);
        $this->stripeAmountValidator = $this->createMock(StripeAmountValidatorInterface::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->paymentMethod = new StripePaymentElementMethod(
            $this->stripePaymentElementConfig,
            $this->stripePaymentActionExecutor,
            $this->stripeAmountValidator,
            $this->paymentTransactionProvider,
            ['group1', 'group2']
        );
        $this->setUpLoggerMock($this->paymentMethod);
    }

    public function testGetIdentifier(): void
    {
        $expectedIdentifier = 'stripe_payment_element_42';
        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodIdentifier')
            ->willReturn($expectedIdentifier);

        self::assertEquals($expectedIdentifier, $this->paymentMethod->getIdentifier());
    }

    public function testIsApplicableForGroup(): void
    {
        self::assertTrue($this->paymentMethod->isApplicableForGroup('group1'));
        self::assertTrue($this->paymentMethod->isApplicableForGroup('group2'));
        self::assertFalse($this->paymentMethod->isApplicableForGroup('invalid_group'));
    }

    public function testSupportsWhenSupported(): void
    {
        $this->stripePaymentActionExecutor
            ->expects(self::once())
            ->method('isSupportedByActionName')
            ->with(PaymentMethodInterface::PURCHASE)
            ->willReturn(true);

        self::assertTrue($this->paymentMethod->supports(PaymentMethodInterface::PURCHASE));
    }

    public function testSupportsWhenNotSupported(): void
    {
        $this->stripePaymentActionExecutor
            ->expects(self::once())
            ->method('isSupportedByActionName')
            ->with(PaymentMethodInterface::VALIDATE)
            ->willReturn(false);

        self::assertFalse($this->paymentMethod->supports(PaymentMethodInterface::VALIDATE));
    }

    public function testIsApplicableWhenValid(): void
    {
        $paymentContext = new PaymentContext(
            [PaymentContext::FIELD_TOTAL => 123.45, PaymentContext::FIELD_CURRENCY => 'USD']
        );

        $this->stripeAmountValidator
            ->expects(self::once())
            ->method('isAboveMinimum')
            ->with($paymentContext->getTotal(), $paymentContext->getCurrency())
            ->willReturn(true);
        $this->stripeAmountValidator
            ->expects(self::once())
            ->method('isBelowMaximum')
            ->with($paymentContext->getTotal(), $paymentContext->getCurrency())
            ->willReturn(true);

        self::assertTrue($this->paymentMethod->isApplicable($paymentContext));
    }

    public function testIsApplicableWhenBelowMinimum(): void
    {
        $paymentContext = new PaymentContext(
            [PaymentContext::FIELD_TOTAL => 123.45, PaymentContext::FIELD_CURRENCY => 'USD']
        );

        $this->stripeAmountValidator
            ->expects(self::once())
            ->method('isAboveMinimum')
            ->with($paymentContext->getTotal(), $paymentContext->getCurrency())
            ->willReturn(false);
        $this->stripeAmountValidator
            ->expects(self::never())
            ->method('isBelowMaximum');

        self::assertFalse($this->paymentMethod->isApplicable($paymentContext));
    }

    public function testIsApplicableWhenAboveMaximum(): void
    {
        $paymentContext = new PaymentContext(
            [PaymentContext::FIELD_TOTAL => 123.45, PaymentContext::FIELD_CURRENCY => 'USD']
        );

        $this->stripeAmountValidator
            ->expects(self::once())
            ->method('isAboveMinimum')
            ->with($paymentContext->getTotal(), $paymentContext->getCurrency())
            ->willReturn(true);
        $this->stripeAmountValidator
            ->expects(self::once())
            ->method('isBelowMaximum')
            ->with($paymentContext->getTotal(), $paymentContext->getCurrency())
            ->willReturn(false);

        self::assertFalse($this->paymentMethod->isApplicable($paymentContext));
    }

    public function testExecuteWhenSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);

        $stripePaymentIntent = new StripePaymentIntent('pi_123');

        $stripeActionResult = new StripePaymentIntentActionResult(
            successful: true,
            stripePaymentIntent: $stripePaymentIntent
        );

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($paymentTransaction);

        $this->stripePaymentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new StripePaymentIntentAction(
                    actionName: PaymentMethodInterface::PURCHASE,
                    stripePaymentIntentConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $paymentTransaction
                )
            )
            ->willReturn($stripeActionResult);

        $this->assertLoggerNotCalled();

        $expectedResult = [
            'successful' => true,
        ];

        self::assertSame(
            $expectedResult,
            $this->paymentMethod->execute(PaymentMethodInterface::PURCHASE, $paymentTransaction)
        );
    }

    public function testExecuteWhenException(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($paymentTransaction);

        $exception = new \RuntimeException('Payment failed');
        $this->stripePaymentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new StripePaymentIntentAction(
                    actionName: PaymentMethodInterface::PURCHASE,
                    stripePaymentIntentConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $paymentTransaction
                )
            )
            ->willThrowException($exception);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to execute a payment action {action} for transaction #{paymentTransactionId}: {message}',
                [
                    'action' => PaymentMethodInterface::PURCHASE,
                    'message' => $exception->getMessage(),
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'throwable' => $exception,
                ]
            );

        $expectedResult = [
            'successful' => false,
            'error' => 'Payment failed',
        ];
        self::assertSame(
            $expectedResult,
            $this->paymentMethod->execute(PaymentMethodInterface::PURCHASE, $paymentTransaction)
        );
    }

    public function testOnWebhookEventWhenSuccess(): void
    {
        $stripeEvent = new StripeEvent('evt_123');
        $stripeEvent->type = 'payment_intent.succeeded';

        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);

        $stripeWebhookEvent = new StripeWebhookEvent($stripeEvent, []);
        $stripeWebhookEvent->setPaymentTransaction($paymentTransaction);

        $stripePaymentIntent = new StripePaymentIntent('pi_123');

        $stripeActionResult = new StripePaymentIntentActionResult(
            successful: true,
            stripePaymentIntent: $stripePaymentIntent
        );

        $this->stripePaymentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new StripePaymentIntentWebhookAction(
                    stripeEvent: $stripeEvent,
                    stripePaymentIntentConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $paymentTransaction,
                )
            )
            ->willReturn($stripeActionResult);

        $this->assertLoggerNotCalled();

        $this->paymentMethod->onWebhookEvent($stripeWebhookEvent);

        self::assertEquals(Response::HTTP_OK, $stripeWebhookEvent->getResponse()->getStatusCode());
    }

    public function testOnWebhookEventWhenFailure(): void
    {
        $stripeEvent = new StripeEvent('evt_123');
        $stripeEvent->type = 'payment_intent.payment_failed';

        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);

        $stripeWebhookEvent = new StripeWebhookEvent($stripeEvent, []);
        $stripeWebhookEvent->setPaymentTransaction($paymentTransaction);

        $stripePaymentIntent = new StripePaymentIntent('pi_123');

        $stripeActionResult = new StripePaymentIntentActionResult(
            successful: false,
            stripePaymentIntent: $stripePaymentIntent
        );

        $this->stripePaymentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new StripePaymentIntentWebhookAction(
                    stripeEvent: $stripeEvent,
                    stripePaymentIntentConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $paymentTransaction,
                )
            )
            ->willReturn($stripeActionResult);

        $this->assertLoggerNotCalled();

        $this->paymentMethod->onWebhookEvent($stripeWebhookEvent);

        self::assertEquals(Response::HTTP_FORBIDDEN, $stripeWebhookEvent->getResponse()->getStatusCode());
    }

    public function testOnWebhookEventWhenException(): void
    {
        $stripeEvent = new StripeEvent('evt_123');
        $stripeEvent->type = 'payment_intent.succeeded';

        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);

        $stripeWebhookEvent = new StripeWebhookEvent($stripeEvent, []);
        $stripeWebhookEvent->setPaymentTransaction($paymentTransaction);

        $successEvent = new StripeEvent('evt_123');
        $successEvent->type = 'payment_intent.succeeded';

        $exception = new \RuntimeException('Connection error');
        $this->stripePaymentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willThrowException($exception);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to process the Stripe Event webhook for payment transaction #{paymentTransactionId}: {message}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'message' => $exception->getMessage(),
                    'stripeEvent' => $stripeEvent->toArray(),
                    'throwable' => $exception,
                ]
            );

        $this->paymentMethod->onWebhookEvent($stripeWebhookEvent);
    }
}
