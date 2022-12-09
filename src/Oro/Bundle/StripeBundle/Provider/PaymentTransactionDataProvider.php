<?php

namespace Oro\Bundle\StripeBundle\Provider;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;

/**
 * Provides data about payment transactions.
 */
class PaymentTransactionDataProvider
{
    private PaymentTransactionRepository $transactionRepository;

    public function __construct(PaymentTransactionRepository $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
    }

    public function getAvailableAmountToRefund(PaymentTransaction $sourceTransaction): float
    {
        $transactions = $this->transactionRepository->findSuccessfulRelatedTransactionsByAction(
            $sourceTransaction,
            PaymentMethodInterface::REFUND
        );

        $amount = 0.00;
        if (!empty($transactions)) {
            $refundedAmounts = array_map(
                fn (PaymentTransaction $transaction) => $this->formatAmount(
                    (float)$transaction->getAmount()
                ),
                $transactions
            );
            $amount = array_sum($refundedAmounts);
        }

        return $this->formatAmount($sourceTransaction->getAmount() - $amount);
    }

    private function formatAmount(float $amount): float
    {
        return round($amount, 2);
    }
}
