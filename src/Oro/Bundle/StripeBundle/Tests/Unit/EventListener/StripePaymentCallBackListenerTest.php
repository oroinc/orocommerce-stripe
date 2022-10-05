<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventListener;

use Monolog\Logger;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Event\CallbackReturnEvent;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentResultMessageProviderInterface;
use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\EventListener\StripePaymentCallBackListener;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;

class StripePaymentCallBackListenerTest extends TestCase
{
    /** @var PaymentMethodProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private PaymentMethodProviderInterface $paymentMethodProvider;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Session */
    private Session $session;

    /** @var PaymentResultMessageProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private PaymentResultMessageProviderInterface $paymentResult;

    /** @var Logger|\PHPUnit\Framework\MockObject\MockObject */
    private Logger $logger;
    private StripePaymentCallBackListener $listener;

    protected function setUp(): void
    {
        $this->paymentMethodProvider = $this->createMock(PaymentMethodProviderInterface::class);
        $this->session = $this->createMock(Session::class);
        $this->paymentResult = $this->createMock(PaymentResultMessageProviderInterface::class);
        $this->logger = $this->createMock(Logger::class);

        $this->listener = new StripePaymentCallBackListener(
            $this->paymentMethodProvider,
            $this->session,
            $this->paymentResult
        );
        $this->listener->setLogger($this->logger);
    }

    public function testOnReturnWithoutPaymentTransaction(): void
    {
        $event = new CallbackReturnEvent([]);
        $this->listener->onReturn($event);
    }

    public function testOnReturnWithWrongPaymentMethod(): void
    {
        $event = new CallbackReturnEvent([]);
        $event->setPaymentTransaction((new PaymentTransaction())->setPaymentMethod('not_stripe'));

        $this->paymentMethodProvider->expects($this->once())
            ->method('hasPaymentMethod')
            ->with('not_stripe')
            ->willReturn(false);

        $this->listener->onReturn($event);
    }

    public function testOnReturnSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod('stripe');
        $paymentTransaction->setTransactionOptions(['additionalData' => '']);

        $event = new CallbackReturnEvent(['paymentIntentId' => 'testIntentId']);
        $event->setPaymentTransaction($paymentTransaction);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->expects($this->once())
            ->method('execute')
            ->with(PaymentActionInterface::CONFIRM_ACTION, $paymentTransaction)
            ->willReturn(['successful' => true]);

        $this->paymentMethodProvider->expects($this->once())
            ->method('hasPaymentMethod')
            ->with('stripe')
            ->willReturn(true);

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('stripe')
            ->willReturn($paymentMethod);

        $this->listener->onReturn($event);

        $this->assertEquals(Response::HTTP_OK, $event->getResponse()->getStatusCode());
        $additionalData = json_decode(
            $paymentTransaction->getTransactionOptions()['additionalData'],
            JSON_OBJECT_AS_ARRAY
        );
        $this->assertEquals(['paymentIntentId' => 'testIntentId'], $additionalData);
    }

    public function testOnReturnPaymentFailed(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod('stripe');
        $paymentTransaction->setTransactionOptions(['additionalData' => '']);

        $event = new CallbackReturnEvent(['setupIntentId' => 'testIntentId']);
        $event->setPaymentTransaction($paymentTransaction);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->expects($this->once())
            ->method('execute')
            ->with(PaymentActionInterface::CONFIRM_ACTION, $paymentTransaction)
            ->willReturn(['successful' => false]);

        $this->paymentMethodProvider->expects($this->once())
            ->method('hasPaymentMethod')
            ->with('stripe')
            ->willReturn(true);

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('stripe')
            ->willReturn($paymentMethod);

        $this->listener->onReturn($event);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $event->getResponse()->getStatusCode());
        $additionalData = json_decode(
            $paymentTransaction->getTransactionOptions()['additionalData'],
            JSON_OBJECT_AS_ARRAY
        );
        $this->assertEquals(['setupIntentId' => 'testIntentId'], $additionalData);
    }

    public function testOnReturnFailedWithRedirect(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod('stripe');
        $paymentTransaction->setTransactionOptions([
            'additionalData' => '',
            'failureUrl' => 'http://failed.com'
        ]);

        $event = new CallbackReturnEvent(['paymentIntentId' => 'testIntentId']);
        $event->setPaymentTransaction($paymentTransaction);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->expects($this->once())
            ->method('execute')
            ->with(PaymentActionInterface::CONFIRM_ACTION, $paymentTransaction)
            ->willReturn(['successful' => false]);

        $this->paymentMethodProvider->expects($this->once())
            ->method('hasPaymentMethod')
            ->with('stripe')
            ->willReturn(true);

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('stripe')
            ->willReturn($paymentMethod);

        $flashBag = new FlashBag();
        $this->session->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBag);

        $this->paymentResult->expects($this->once())
            ->method('getErrorMessage')
            ->with($paymentTransaction)
            ->willReturn('Payment failed');

        $this->listener->onReturn($event);

        $this->assertEquals(Response::HTTP_FOUND, $event->getResponse()->getStatusCode());
        $this->assertEquals(['Payment failed'], $flashBag->get('error'));
    }

    public function testOnReturnWithStripeApiException(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod('stripe');
        $paymentTransaction->setTransactionOptions(['additionalData' => '']);

        $event = new CallbackReturnEvent(['paymentIntentId' => 'testIntentId']);
        $event->setPaymentTransaction($paymentTransaction);

        $exception = new StripeApiException('Payment failed', '222', '111');

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->expects($this->once())
            ->method('execute')
            ->with(PaymentActionInterface::CONFIRM_ACTION, $paymentTransaction)
            ->willThrowException($exception);

        $this->paymentMethodProvider->expects($this->once())
            ->method('hasPaymentMethod')
            ->with('stripe')
            ->willReturn(true);

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('stripe')
            ->willReturn($paymentMethod);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($exception->getMessage(), [
                'error' => $exception->getMessage(),
                'stripe_error_code' => $exception->getStripeErrorCode(),
                'decline_code' => $exception->getDeclineCode(),
                'exception' => $exception
            ]);

        $this->listener->onReturn($event);
    }

    public function testOnReturnWithException(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod('stripe');
        $paymentTransaction->setTransactionOptions(['additionalData' => '']);

        $event = new CallbackReturnEvent(['paymentIntentId' => 'testIntentId']);
        $event->setPaymentTransaction($paymentTransaction);

        $exception = new \Exception('Payment failed');

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->expects($this->once())
            ->method('execute')
            ->with(PaymentActionInterface::CONFIRM_ACTION, $paymentTransaction)
            ->willThrowException($exception);

        $this->paymentMethodProvider->expects($this->once())
            ->method('hasPaymentMethod')
            ->with('stripe')
            ->willReturn(true);

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('stripe')
            ->willReturn($paymentMethod);

        $this->logger->expects($this->once())
            ->method('critical')
            ->with($exception->getMessage(), [
                'error' => $exception->getMessage(),
                'exception' => $exception
            ]);

        $this->listener->onReturn($event);
    }

    public function testOnReturnWithPartialSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod('stripe');
        $paymentTransaction->setTransactionOptions([
            'additionalData' => '',
            'partiallyPaidUrl' => '/test'
        ]);

        $event = new CallbackReturnEvent(['setupIntentId' => 'testIntentId']);
        $event->setPaymentTransaction($paymentTransaction);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->expects($this->once())
            ->method('execute')
            ->with(PaymentActionInterface::CONFIRM_ACTION, $paymentTransaction)
            ->willReturn([
                'successful' => false,
                'is_multi_transaction' => true,
                'has_successful' => true
            ]);

        $this->paymentMethodProvider->expects($this->once())
            ->method('hasPaymentMethod')
            ->with('stripe')
            ->willReturn(true);

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('stripe')
            ->willReturn($paymentMethod);

        $this->listener->onReturn($event);
        $this->assertEquals(Response::HTTP_FOUND, $event->getResponse()->getStatusCode());
        $additionalData = json_decode(
            $paymentTransaction->getTransactionOptions()['additionalData'],
            JSON_OBJECT_AS_ARRAY
        );
        $this->assertEquals(['setupIntentId' => 'testIntentId'], $additionalData);
    }
}
