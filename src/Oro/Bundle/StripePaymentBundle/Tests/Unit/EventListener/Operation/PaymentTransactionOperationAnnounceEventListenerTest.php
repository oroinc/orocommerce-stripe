<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\EventListener\Operation;

use Oro\Bundle\ActionBundle\Event\OperationAnnounceEvent;
use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\ActionBundle\Model\OperationDefinition;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\EventListener\Operation\PaymentTransactionOperationAnnounceEventListener;
use Oro\Bundle\StripePaymentBundle\Integration\StripePaymentElement\StripePaymentElementIntegrationChannelType;
use PHPUnit\Framework\TestCase;

/**
 * The listener is subscribed to the "announce" event of both "oro_order_payment_transaction_cancel" and
 * "oro_order_payment_transaction_refund", and must disallow them for Stripe payment transactions only.
 * See BB-25652, where they were disallowed for every payment method and the "Cancel Authorization" and
 * "Refund" buttons disappeared from all non-Stripe integrations.
 */
final class PaymentTransactionOperationAnnounceEventListenerTest extends TestCase
{
    private PaymentTransactionOperationAnnounceEventListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->listener = new PaymentTransactionOperationAnnounceEventListener(
            StripePaymentElementIntegrationChannelType::TYPE
        );
    }

    /**
     * @dataProvider paymentMethodDataProvider
     */
    public function testOnOperationAnnounce(string $paymentMethod, bool $expectedAllowed): void
    {
        $event = $this->createEvent($paymentMethod);

        $this->listener->onOperationAnnounce($event);

        self::assertSame($expectedAllowed, $event->isAllowed());
    }

    public function paymentMethodDataProvider(): array
    {
        return [
            'stripe payment element' => [
                'paymentMethod' => 'stripe_payment_element_1',
                'expectedAllowed' => false,
            ],
            'stripe payment element with a multi-digit channel id' => [
                'paymentMethod' => 'stripe_payment_element_42',
                'expectedAllowed' => false,
            ],
            'another payment method' => [
                'paymentMethod' => 'authorize_net_1',
                'expectedAllowed' => true,
            ],
            'payment method without a channel id' => [
                'paymentMethod' => 'payment_term',
                'expectedAllowed' => true,
            ],
            // The channel id is recognized only when it is numeric, otherwise the whole identifier
            // becomes the prefix and no longer equals the Stripe one.
            'stripe payment element with a non-numeric suffix' => [
                'paymentMethod' => 'stripe_payment_element_abc',
                'expectedAllowed' => true,
            ],
            'payment method without a separator' => [
                'paymentMethod' => 'sample',
                'expectedAllowed' => true,
            ],
        ];
    }

    private function createEvent(string $paymentMethod): OperationAnnounceEvent
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod($paymentMethod);

        return new OperationAnnounceEvent(
            new ActionData(['data' => $paymentTransaction]),
            new OperationDefinition()
        );
    }
}
