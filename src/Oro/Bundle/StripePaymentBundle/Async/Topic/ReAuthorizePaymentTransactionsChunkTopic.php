<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Async\Topic;

use Oro\Component\MessageQueue\Topic\AbstractTopic;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A topic to renew a chunk of payment transactions for uncaptured payments that are about to expire.
 */
class ReAuthorizePaymentTransactionsChunkTopic extends AbstractTopic
{
    public const string JOB_ID = 'jobId';
    public const string PAYMENT_TRANSACTIONS = 'paymentTransactions';

    #[\Override]
    public static function getName(): string
    {
        return 'oro.stripe_payment.re_authorize_payment_transactions.chunk';
    }

    #[\Override]
    public static function getDescription(): string
    {
        return 'Renews a chunk of payment transactions for uncaptured payments that are about to expire.';
    }

    #[\Override]
    public function configureMessageBody(OptionsResolver $resolver): void
    {
        $resolver
            ->define(self::JOB_ID)
            ->required()
            ->allowedTypes('int');

        $resolver
            ->define(self::PAYMENT_TRANSACTIONS)
            ->required()
            ->allowedTypes('int[]');
    }
}
