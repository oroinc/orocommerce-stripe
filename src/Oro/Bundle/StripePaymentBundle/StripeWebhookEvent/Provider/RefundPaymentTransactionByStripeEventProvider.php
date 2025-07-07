<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Stripe\Event as StripeEvent;
use Stripe\Refund as StripeRefund;

/**
 * Handles payment transaction lookup for Stripe refund events.
 *
 * Finds the original payment transaction that the refund was created for.
 */
class RefundPaymentTransactionByStripeEventProvider implements PaymentTransactionByStripeEventProviderInterface
{
    public function __construct(private readonly PaymentTransactionRepository $paymentTransactionRepository)
    {
    }

    #[\Override]
    public function isApplicable(StripeEvent $event): bool
    {
        return $event->type === 'refund.updated';
    }

    #[\Override]
    public function findPaymentTransactionByStripeEvent(StripeEvent $event): ?PaymentTransaction
    {
        /** @var StripeRefund|null $object */
        $object = $event->data->object ?? null;
        $reference = $object->payment_intent ?? null;

        if (!$reference) {
            return null;
        }

        return $this->paymentTransactionRepository
            ->findOneBy(
                [
                    'reference' => $reference,
                    'action' => [
                        PaymentMethodInterface::PURCHASE,
                        PaymentMethodInterface::CHARGE,
                        PaymentMethodInterface::CAPTURE,
                    ],
                ],
                ['id' => 'DESC', 'active' => 'DESC']
            );
    }
}
