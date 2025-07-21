<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\EventListener\PaymentCallback;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Event\AbstractCallbackEvent;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\EventListener\PaymentCallback\StripePaymentIntentsWebhookCallbackListener;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEvent;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEventHandlerInterface;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;
use Symfony\Component\HttpFoundation\Response;

final class StripePaymentIntentsWebhookCallbackListenerTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private StripePaymentIntentsWebhookCallbackListener $listener;

    private MockObject&PaymentMethodProviderInterface $paymentMethodProvider;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    protected function setUp(): void
    {
        $this->paymentMethodProvider = $this->createMock(PaymentMethodProviderInterface::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->listener = new StripePaymentIntentsWebhookCallbackListener(
            $this->paymentMethodProvider,
            $this->paymentTransactionProvider
        );

        $this->setUpLoggerMock($this->listener);
    }

    public function testOnPaymentCallbackWhenNonStripeWebhookEvent(): void
    {
        $event = $this->createMock(AbstractCallbackEvent::class);

        $this->paymentMethodProvider
            ->expects(self::never())
            ->method('hasPaymentMethod');
        $this->paymentTransactionProvider
            ->expects(self::never())
            ->method('savePaymentTransaction');

        $this->assertLoggerNotCalled();

        $this->listener->onPaymentCallback($event);
    }

    public function testOnPaymentCallbackWhenNoPaymentTransaction(): void
    {
        $event = $this->createMock(StripeWebhookEvent::class);
        $event
            ->expects(self::once())
            ->method('getPaymentTransaction')
            ->willReturn(null);

        $this->paymentMethodProvider
            ->expects(self::never())
            ->method('hasPaymentMethod');
        $this->paymentTransactionProvider
            ->expects(self::never())
            ->method('savePaymentTransaction');

        $this->assertLoggerNotCalled();

        $this->listener->onPaymentCallback($event);
    }

    public function testOnPaymentCallbackWhenPaymentMethodNotFound(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);
        $paymentTransaction->setPaymentMethod('stripe_payment_element_42');

        $stripeEvent = new StripeEvent('evt_123');
        $stripeEvent->type = 'payment_intent.succeeded';

        $stripeWebhookEvent = new StripeWebhookEvent($stripeEvent);
        $stripeWebhookEvent
            ->setPaymentTransaction($paymentTransaction);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_payment_element_42')
            ->willReturn(false);
        $this->paymentMethodProvider
            ->expects(self::never())
            ->method('getPaymentMethod');
        $this->paymentTransactionProvider
            ->expects(self::never())
            ->method('savePaymentTransaction');

        $this->loggerMock
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Cannot process the webhook request for payment transaction #{paymentTransactionId}: '
                . 'payment method #{paymentMethodIdentifier} is not found',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'paymentMethodIdentifier' => $paymentTransaction->getPaymentMethod(),
                    'stripeEvent' => $stripeWebhookEvent->getStripeEvent()->toArray(),
                ]
            );

        $this->listener->onPaymentCallback($stripeWebhookEvent);
    }

    public function testOnPaymentCallbackWhenPaymentMethodNotImplementsInterface(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);
        $paymentTransaction->setPaymentMethod('stripe_payment_element_42');

        $stripeEvent = new StripeEvent('evt_123');
        $stripeEvent->type = 'payment_intent.succeeded';

        $stripeWebhookEvent = new StripeWebhookEvent($stripeEvent);
        $stripeWebhookEvent
            ->setPaymentTransaction($paymentTransaction);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_payment_element_42')
            ->willReturn(true);
        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_payment_element_42')
            ->willReturn($this->createMock(PaymentMethodInterface::class));
        $this->paymentTransactionProvider
            ->expects(self::never())
            ->method('savePaymentTransaction');

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to process the Stripe webhook request for payment transaction #{paymentTransactionId}: '
                . 'payment method #{paymentMethodIdentifier} does not implement {webhookHandlerInterface}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'paymentMethodIdentifier' => $paymentTransaction->getPaymentMethod(),
                    'webhookHandlerInterface' => StripeWebhookEventHandlerInterface::class,
                    'stripeEvent' => $stripeWebhookEvent->getStripeEvent()->toArray(),
                ]
            );

        $this->listener->onPaymentCallback($stripeWebhookEvent);
    }

    public function testOnPaymentCallbackSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);
        $paymentTransaction->setPaymentMethod('stripe_payment_element_42');

        $stripeEvent = new StripeEvent('evt_123');
        $stripeEvent->type = 'payment_intent.succeeded';

        $stripeWebhookEvent = new StripeWebhookEvent($stripeEvent);
        $stripeWebhookEvent
            ->setPaymentTransaction($paymentTransaction);

        $paymentMethod = $this->createMock(StripeWebhookEventHandlerInterface::class);
        $paymentMethod
            ->expects(self::once())
            ->method('onWebhookEvent')
            ->with($stripeWebhookEvent)
            ->willReturnCallback(static function (StripeWebhookEvent $stripeWebhookEvent) {
                $stripeWebhookEvent->markSuccessful();
            });

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_payment_element_42')
            ->willReturn(true);
        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_payment_element_42')
            ->willReturn($paymentMethod);
        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($paymentTransaction);

        $this->assertLoggerNotCalled();

        $this->listener->onPaymentCallback($stripeWebhookEvent);

        self::assertEquals(Response::HTTP_OK, $stripeWebhookEvent->getResponse()->getStatusCode());
    }

    public function testOnPaymentCallbackWithException(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 123);
        $paymentTransaction->setPaymentMethod('stripe_payment_element_42');

        $stripeEvent = new StripeEvent('evt_123');
        $stripeEvent->type = 'payment_intent.succeeded';

        $stripeWebhookEvent = new StripeWebhookEvent($stripeEvent);
        $stripeWebhookEvent
            ->setPaymentTransaction($paymentTransaction);

        $exception = new \RuntimeException('Test error');
        $paymentMethod = $this->createMock(StripeWebhookEventHandlerInterface::class);
        $paymentMethod
            ->expects(self::once())
            ->method('onWebhookEvent')
            ->with($stripeWebhookEvent)
            ->willThrowException($exception);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_payment_element_42')
            ->willReturn(true);
        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_payment_element_42')
            ->willReturn($paymentMethod);
        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($paymentTransaction);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to process the Stripe webhook request for payment transaction #{paymentTransactionId}: '
                . '{message}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'paymentMethodIdentifier' => $paymentTransaction->getPaymentMethod(),
                    'message' => $exception->getMessage(),
                    'stripeEvent' => $stripeWebhookEvent->getStripeEvent()->toArray(),
                    'throwable' => $exception,
                ]
            );

        $this->listener->onPaymentCallback($stripeWebhookEvent);

        self::assertEquals(Response::HTTP_FORBIDDEN, $stripeWebhookEvent->getResponse()->getStatusCode());
    }
}
