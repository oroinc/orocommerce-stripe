<?php

namespace Oro\Bundle\StripeBundle\Action;

use Oro\Bundle\PaymentBundle\Action\PaymentTransactionRefundAction;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Action to refund captured payments partially.
 */
class PaymentTransactionPartialRefundAction extends PaymentTransactionRefundAction
{
    protected function configureOptionsResolver(OptionsResolver $resolver)
    {
        parent::configureOptionsResolver($resolver);
        $resolver->setRequired('amount');
    }

    protected function configureValuesResolver(OptionsResolver $resolver)
    {
        parent::configureValuesResolver($resolver);
        $resolver->setRequired('amount');
    }

    protected function createTransaction(PaymentTransaction $sourceTransaction, array $options): PaymentTransaction
    {
        $refundPaymentTransaction = parent::createTransaction($sourceTransaction, $options);
        $refundPaymentTransaction->setAmount($options['amount']);

        return $refundPaymentTransaction;
    }
}
