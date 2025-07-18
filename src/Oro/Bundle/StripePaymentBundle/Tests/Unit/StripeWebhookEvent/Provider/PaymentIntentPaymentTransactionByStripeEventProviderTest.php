<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEvent\Provider;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider\PaymentIntentPaymentTransactionByStripeEventProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;
use Stripe\StripeObject;

final class PaymentIntentPaymentTransactionByStripeEventProviderTest extends TestCase
{
    private PaymentIntentPaymentTransactionByStripeEventProvider $provider;

    private MockObject&PaymentTransactionRepository $paymentTransactionRepository;

    protected function setUp(): void
    {
        $this->paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $this->provider = new PaymentIntentPaymentTransactionByStripeEventProvider(
            $this->paymentTransactionRepository
        );
    }

    /**
     * @dataProvider applicableEventTypesDataProvider
     */
    public function testIsApplicableForApplicableEventTypes(string $eventType): void
    {
        $event = new StripeEvent('evt_123');
        $event->type = $eventType;
        $event->data = new StripeObject();
        $event->data->object = new StripeObject();

        self::assertTrue($this->provider->isApplicable($event));
    }

    public function applicableEventTypesDataProvider(): array
    {
        return [
            'payment_intent.succeeded' => ['payment_intent.succeeded'],
            'payment_intent.payment_failed' => ['payment_intent.payment_failed'],
            'payment_intent.canceled' => ['payment_intent.canceled'],
        ];
    }

    /**
     * @dataProvider nonApplicableEventTypesDataProvider
     */
    public function testIsApplicableForNonApplicableEventTypes(string $eventType): void
    {
        $event = new StripeEvent('evt_123');
        $event->type = $eventType;
        $event->data = new StripeObject();
        $event->data->object = new StripeObject();

        self::assertFalse($this->provider->isApplicable($event));
    }

    public function nonApplicableEventTypesDataProvider(): array
    {
        return [
            'charge.succeeded' => ['charge.succeeded'],
            'invoice.paid' => ['invoice.paid'],
            'customer.created' => ['customer.created'],
        ];
    }

    public function testFindPaymentTransactionWhenNoEventDataObject(): void
    {
        $event = new StripeEvent('evt_123');
        $event->type = 'payment_intent.succeeded';
        $event->data = new StripeObject();
        $event->data->object = new StripeObject();

        $event->data = new StripeObject();

        self::assertNull($this->provider->findPaymentTransactionByStripeEvent($event));
    }

    public function testFindPaymentTransactionWhenNoMetadata(): void
    {
        $event = new StripeEvent('evt_123');
        $event->type = 'payment_intent.succeeded';
        $event->data = new StripeObject();
        $event->data->object = new StripeObject();

        self::assertNull($this->provider->findPaymentTransactionByStripeEvent($event));
    }

    public function testFindPaymentTransactionWhenNoAccessIdentifier(): void
    {
        $event = new StripeEvent('evt_123');
        $event->type = 'payment_intent.succeeded';
        $event->data = new StripeObject();
        $event->data->object = new StripeObject();
        $event->data->object->metadata = ['payment_transaction_access_token' => 'token123'];

        self::assertNull($this->provider->findPaymentTransactionByStripeEvent($event));
    }

    public function testFindPaymentTransactionWhenNoAccessToken(): void
    {
        $event = new StripeEvent('evt_123');
        $event->type = 'payment_intent.succeeded';
        $event->data = new StripeObject();
        $event->data->object = new StripeObject();
        $event->data->object->metadata = ['payment_transaction_access_identifier' => 'id123'];

        self::assertNull($this->provider->findPaymentTransactionByStripeEvent($event));
    }

    public function testFindPaymentTransactionWhenRepositoryReturnsNull(): void
    {
        $event = new StripeEvent('evt_123');
        $event->type = 'payment_intent.succeeded';
        $event->data = new StripeObject();
        $event->data->object = new StripeObject();
        $event->data->object->metadata = [
            'payment_transaction_access_identifier' => 'id123',
            'payment_transaction_access_token' => 'token123',
        ];

        $this->paymentTransactionRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([
                'accessIdentifier' => 'id123',
                'accessToken' => 'token123',
            ])
            ->willReturn(null);

        self::assertNull($this->provider->findPaymentTransactionByStripeEvent($event));
    }

    public function testFindPaymentTransactionWhenRepositoryReturnsTransaction(): void
    {
        $event = new StripeEvent('evt_123');
        $event->type = 'payment_intent.succeeded';
        $event->data = new StripeObject();
        $event->data->object = new StripeObject();
        $event->data->object->metadata = [
            'payment_transaction_access_identifier' => 'id123',
            'payment_transaction_access_token' => 'token123',
        ];

        $transaction = new PaymentTransaction();

        $this->paymentTransactionRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([
                'accessIdentifier' => 'id123',
                'accessToken' => 'token123',
            ])
            ->willReturn($transaction);

        self::assertSame($transaction, $this->provider->findPaymentTransactionByStripeEvent($event));
    }
}
