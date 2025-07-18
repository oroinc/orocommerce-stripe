<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\EventListener\PaymentCallback;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Event\AbstractCallbackEvent;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\StripePaymentBundle\EventListener\PaymentCallback\StripePaymentIntentsReturnCallbackListener;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\ConfirmStripeActionExecutor;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class StripePaymentIntentsReturnCallbackListenerTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private PaymentMethodProviderInterface&MockObject $paymentMethodProvider;

    private AbstractCallbackEvent&MockObject $event;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private StripePaymentIntentsReturnCallbackListener $listener;

    protected function setUp(): void
    {
        $this->paymentMethodProvider = $this->createMock(PaymentMethodProviderInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->event = $this->createMock(AbstractCallbackEvent::class);

        $this->listener = new StripePaymentIntentsReturnCallbackListener(
            $this->paymentMethodProvider
        );
        $this->setUpLoggerMock($this->listener);
    }

    public function testOnPaymentCallbackWithoutTransaction(): void
    {
        $this->event
            ->expects(self::once())
            ->method('getPaymentTransaction')
            ->willReturn(null);
        $this->event
            ->expects(self::never())
            ->method('markSuccessful');
        $this->event
            ->expects(self::never())
            ->method('markFailed');

        $this->listener->onPaymentCallback($this->event);
    }

    public function testOnPaymentCallbackWithoutPaymentMethod(): void
    {
        $transaction = (new PaymentTransaction())
            ->setPaymentMethod('stripe_1');

        $this->event
            ->expects(self::once())
            ->method('getPaymentTransaction')
            ->willReturn($transaction);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_1')
            ->willReturn(false);

        $this->event
            ->expects(self::never())
            ->method('markSuccessful');
        $this->event
            ->expects(self::never())
            ->method('markFailed');

        $this->listener->onPaymentCallback($this->event);
    }

    public function testOnPaymentCallbackSuccessful(): void
    {
        $transaction = (new PaymentTransaction())
            ->setPaymentMethod('stripe_1')
            ->addTransactionOption('returnData', ['existing' => 'data']);

        $this->event
            ->expects(self::once())
            ->method('getPaymentTransaction')
            ->willReturn($transaction);
        $this->event
            ->expects(self::once())
            ->method('getData')
            ->willReturn(['new' => 'data']);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_1')
            ->willReturn(true);
        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_1')
            ->willReturn($this->paymentMethod);

        $this->paymentMethod
            ->expects(self::once())
            ->method('execute')
            ->with(ConfirmStripeActionExecutor::ACTION_NAME, $transaction)
            ->willReturn(['successful' => true]);

        $this->event
            ->expects(self::once())
            ->method('markSuccessful');
        $this->event
            ->expects(self::never())
            ->method('markFailed');

        $this->listener->onPaymentCallback($this->event);

        self::assertEquals(['existing' => 'data', 'new' => 'data'], $transaction->getTransactionOption('returnData'));
    }

    public function testOnPaymentCallbackFailedWithRedirect(): void
    {
        $transaction = (new PaymentTransaction())
            ->setPaymentMethod('stripe_1')
            ->setTransactionOptions([
                'returnData' => null,
                'failureUrl' => '/failure',
                'partiallyPaidUrl' => null,
            ]);

        $this->event
            ->expects(self::once())
            ->method('getPaymentTransaction')
            ->willReturn($transaction);
        $this->event
            ->expects(self::once())
            ->method('getData')
            ->willReturn(['new' => 'data']);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_1')
            ->willReturn(true);
        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_1')
            ->willReturn($this->paymentMethod);

        $this->paymentMethod
            ->expects(self::once())
            ->method('execute')
            ->with(ConfirmStripeActionExecutor::ACTION_NAME, $transaction)
            ->willReturn(['successful' => false]);

        $this->event
            ->expects(self::never())
            ->method('markSuccessful');
        $this->event
            ->expects(self::once())
            ->method('markFailed');
        $this->event
            ->expects(self::once())
            ->method('setResponse')
            ->with(new RedirectResponse('/failure'));

        $this->listener->onPaymentCallback($this->event);

        self::assertEquals(['new' => 'data'], $transaction->getTransactionOption('returnData'));
    }

    public function testOnPaymentCallbackPartiallyPaidWithRedirect(): void
    {
        $transaction = (new PaymentTransaction())
            ->setPaymentMethod('stripe_1')
            ->setTransactionOptions([
                'returnData' => null,
                'failureUrl' => '/failure',
                'partiallyPaidUrl' => '/partial',
            ]);

        $this->event
            ->expects(self::once())
            ->method('getPaymentTransaction')
            ->willReturn($transaction);
        $this->event
            ->expects(self::once())
            ->method('getData')
            ->willReturn(['new' => 'data']);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_1')
            ->willReturn(true);
        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_1')
            ->willReturn($this->paymentMethod);

        $this->paymentMethod
            ->expects(self::once())
            ->method('execute')
            ->with(ConfirmStripeActionExecutor::ACTION_NAME, $transaction)
            ->willReturn(['successful' => false, 'isPartiallyPaid' => true]);

        $this->event
            ->expects(self::never())
            ->method('markSuccessful');
        $this->event
            ->expects(self::once())
            ->method('markFailed');
        $this->event
            ->expects(self::once())
            ->method('setResponse')
            ->with(new RedirectResponse('/partial'));

        $this->listener->onPaymentCallback($this->event);

        self::assertEquals(['new' => 'data'], $transaction->getTransactionOption('returnData'));
    }

    public function testOnPaymentCallbackWithException(): void
    {
        $exception = new \RuntimeException('Test error');
        $transaction = (new PaymentTransaction())
            ->setPaymentMethod('stripe_1')
            ->setTransactionOptions([
                'returnData' => null,
                'failureUrl' => '/failure',
                'partiallyPaidUrl' => 'partial',
            ]);

        $this->event
            ->expects(self::once())
            ->method('getPaymentTransaction')
            ->willReturn($transaction);
        $this->event
            ->expects(self::once())
            ->method('getData')
            ->willReturn(['new' => 'data']);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_1')
            ->willReturn(true);
        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_1')
            ->willReturn($this->paymentMethod);

        $this->paymentMethod
            ->expects(self::once())
            ->method('execute')
            ->with(ConfirmStripeActionExecutor::ACTION_NAME, $transaction)
            ->willThrowException($exception);

        $this->event
            ->expects(self::never())
            ->method('markSuccessful');
        $this->event
            ->expects(self::once())
            ->method('markFailed');
        $this->event
            ->expects(self::once())
            ->method('setResponse')
            ->with(new RedirectResponse('/failure'));

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with('Failed to handle the return URL for transaction #{transactionId}: {message}', [
                'message' => $exception->getMessage(),
                'throwable' => $exception,
                'transactionId' => $transaction->getId(),
            ]);

        $this->listener->onPaymentCallback($this->event);
    }
}
