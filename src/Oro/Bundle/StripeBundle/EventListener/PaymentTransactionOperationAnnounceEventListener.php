<?php

namespace Oro\Bundle\StripeBundle\EventListener;

use Oro\Bundle\ActionBundle\Event\OperationAnnounceEvent;
use Oro\Bundle\IntegrationBundle\Generator\Prefixed\PrefixedIntegrationIdentifierGenerator;

/**
 * Hides subscribed operations for non-stripe payment transaction.
 *
 * Transaction refund and cancel logic for Stripe has extended logic and replaces default operations.
 * To not override the existing implementation, new operations for Stripe are added and base operations should be hidden
 */
class PaymentTransactionOperationAnnounceEventListener
{
    public function __construct(
        private string $paymentMethodPrefix
    ) {
    }

    public function onOperationAnnounce(OperationAnnounceEvent $event): void
    {
        [$paymentMethodType, $id] = PrefixedIntegrationIdentifierGenerator::parseIdentifier(
            $event->getActionData()->getEntity()->getPaymentMethod()
        );
        if ($paymentMethodType === $this->paymentMethodPrefix) {
            $event->setAllowed(false);
        }
    }
}
