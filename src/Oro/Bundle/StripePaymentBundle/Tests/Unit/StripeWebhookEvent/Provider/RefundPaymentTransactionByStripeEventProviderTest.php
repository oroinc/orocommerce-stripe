<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEvent\Provider;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider\RefundPaymentTransactionByStripeEventProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;
use Stripe\Refund as StripeRefund;
use Stripe\StripeObject;

final class RefundPaymentTransactionByStripeEventProviderTest extends TestCase
{
    private RefundPaymentTransactionByStripeEventProvider $provider;

    private MockObject&PaymentTransactionRepository $paymentTransactionRepository;

    protected function setUp(): void
    {
        $this->paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $this->provider = new RefundPaymentTransactionByStripeEventProvider($this->paymentTransactionRepository);
    }

    public function testIsApplicableForRefundUpdatedEvent(): void
    {
        $stripeEvent = $this->createStripeEvent('refund.updated');
        self::assertTrue($this->provider->isApplicable($stripeEvent));
    }

    public function testIsApplicableForOtherEventTypes(): void
    {
        $stripeEvent = $this->createStripeEvent('charge.refunded');
        self::assertFalse($this->provider->isApplicable($stripeEvent));

        $stripeEvent = $this->createStripeEvent('payment_intent.succeeded');
        self::assertFalse($this->provider->isApplicable($stripeEvent));
    }

    public function testFindPaymentTransactionWithValidPaymentIntentId(): void
    {
        $paymentIntentId = 'pi_123456789';
        $paymentTransaction = new PaymentTransaction();
        $stripeEvent = $this->createStripeEventWithPaymentIntentId($paymentIntentId);

        $this->paymentTransactionRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(
                [
                    'reference' => $paymentIntentId,
                    'action' => [
                        PaymentMethodInterface::PURCHASE,
                        PaymentMethodInterface::CHARGE,
                        PaymentMethodInterface::CAPTURE,
                    ],
                ],
                ['id' => 'DESC', 'active' => 'DESC']
            )
            ->willReturn($paymentTransaction);

        $result = $this->provider->findPaymentTransactionByStripeEvent($stripeEvent);
        self::assertSame($paymentTransaction, $result);
    }

    public function testFindPaymentTransactionWithMissingPaymentIntentId(): void
    {
        $stripeEvent = $this->createStripeEvent('refund.updated');
        $stripeEvent->data->object = new StripeObject();

        $result = $this->provider->findPaymentTransactionByStripeEvent($stripeEvent);
        self::assertNull($result);
    }

    public function testFindPaymentTransactionWithNullObject(): void
    {
        $stripeEvent = $this->createStripeEvent('refund.updated');
        $stripeEvent->data->object = null;

        $result = $this->provider->findPaymentTransactionByStripeEvent($stripeEvent);
        self::assertNull($result);
    }

    public function testFindPaymentTransactionWithNullData(): void
    {
        $stripeEvent = $this->createStripeEvent('refund.updated');
        $stripeEvent->data = null;

        $result = $this->provider->findPaymentTransactionByStripeEvent($stripeEvent);
        self::assertNull($result);
    }

    public function testFindPaymentTransactionWhenNotFound(): void
    {
        $paymentIntent = 'pi_123456789';
        $stripeEvent = $this->createStripeEventWithPaymentIntentId($paymentIntent);

        $this->paymentTransactionRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->willReturn(null);

        $result = $this->provider->findPaymentTransactionByStripeEvent($stripeEvent);
        self::assertNull($result);
    }

    public function testFindPaymentTransactionWithEmptyPaymentIntentId(): void
    {
        $stripeEvent = $this->createStripeEventWithPaymentIntentId('');

        $result = $this->provider->findPaymentTransactionByStripeEvent($stripeEvent);
        self::assertNull($result);
    }

    private function createStripeEvent(string $eventType): StripeEvent
    {
        $stripeEvent = new StripeEvent();
        $stripeEvent->type = $eventType;
        $stripeEvent->data = new StripeObject();
        $stripeEvent->data->object = new StripeObject();

        return $stripeEvent;
    }

    private function createStripeEventWithPaymentIntentId(string $paymentIntentId): StripeEvent
    {
        $stripeEvent = $this->createStripeEvent('refund.updated');
        $stripeEvent->data->object = new StripeRefund();

        if ($paymentIntentId) {
            $stripeEvent->data->object->payment_intent = $paymentIntentId;
        }

        return $stripeEvent;
    }
}
