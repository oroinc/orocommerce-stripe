<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;

/**
 * Provides sub-orders related to a given payment transaction.
 */
class SubOrdersByPaymentTransactionProvider
{
    public function __construct(
        private readonly ManagerRegistry $doctrine
    ) {
    }

    public function hasSubOrders(PaymentTransaction $paymentTransaction): bool
    {
        if (!$this->isOrderSourceEntityClass($paymentTransaction)) {
            return false;
        }

        return $this->doctrine->getRepository(Order::class)
            ->hasSubOrders((int)$paymentTransaction->getEntityIdentifier());
    }

    /**
     * @return array<Order>
     */
    public function getSubOrders(PaymentTransaction $paymentTransaction): array
    {
        if (!$this->isOrderSourceEntityClass($paymentTransaction)) {
            return [];
        }

        return $this->doctrine->getRepository(Order::class)
            ->findSubOrders((int)$paymentTransaction->getEntityIdentifier());
    }

    private function isOrderSourceEntityClass(PaymentTransaction $paymentTransaction): bool
    {
        return $paymentTransaction->getEntityClass() === Order::class;
    }
}
