<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Factory;

use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;

/**
 * Creates a payment transaction for the specified sub-order based on the parent order's payment transaction.
 */
class SubOrderPaymentTransactionFactory
{
    public function __construct(
        private readonly PaymentTransactionProvider $paymentTransactionProvider
    ) {
    }

    public function createSubOrderPaymentTransaction(
        PaymentTransaction $parentOrderPaymentTransaction,
        Order $subOrder
    ): PaymentTransaction {
        $subPaymentTransaction = $this->paymentTransactionProvider->createPaymentTransaction(
            $parentOrderPaymentTransaction->getPaymentMethod(),
            $parentOrderPaymentTransaction->getAction(),
            $subOrder
        );

        $subPaymentTransaction->setAmount($subOrder->getTotal());
        $subPaymentTransaction->setCurrency($subOrder->getCurrency());
        $subPaymentTransaction->setTransactionOptions($parentOrderPaymentTransaction->getTransactionOptions());
        $subPaymentTransaction->setSourcePaymentTransaction($parentOrderPaymentTransaction);

        return $subPaymentTransaction;
    }
}
