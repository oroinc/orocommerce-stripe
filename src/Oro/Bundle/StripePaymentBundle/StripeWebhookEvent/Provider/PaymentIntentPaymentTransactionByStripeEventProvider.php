<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Stripe\Event as StripeEvent;

/**
 * Handles payment transaction lookup for Stripe payment intent events.
 *
 * Supports these event types:
 * - payment_intent.succeeded
 * - payment_intent.payment_failed
 * - payment_intent.canceled
 */
class PaymentIntentPaymentTransactionByStripeEventProvider implements
    PaymentTransactionByStripeEventProviderInterface
{
    private array $applicableEventTypes = [
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'payment_intent.canceled',
    ];

    public function __construct(private readonly PaymentTransactionRepository $paymentTransactionRepository)
    {
    }

    public function setApplicableEventTypes(array $applicableEventTypes): void
    {
        $this->applicableEventTypes = $applicableEventTypes;
    }

    #[\Override]
    public function isApplicable(StripeEvent $event): bool
    {
        return in_array($event->type, $this->applicableEventTypes, true);
    }

    #[\Override]
    public function findPaymentTransactionByStripeEvent(StripeEvent $event): ?PaymentTransaction
    {
        $object = $event->data->object ?? null;
        $accessIdentifier = $object->metadata['payment_transaction_access_identifier'] ?? null;
        $accessToken = $object->metadata['payment_transaction_access_token'] ?? null;

        if (!$accessIdentifier || !$accessToken) {
            return null;
        }

        return $this->paymentTransactionRepository
            ->findOneBy([
                'accessIdentifier' => $accessIdentifier,
                'accessToken' => $accessToken,
            ]);
    }
}
