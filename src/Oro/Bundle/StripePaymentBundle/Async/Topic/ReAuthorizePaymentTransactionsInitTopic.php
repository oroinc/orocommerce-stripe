<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Async\Topic;

use Oro\Component\MessageQueue\Topic\AbstractTopic;
use Oro\Component\MessageQueue\Topic\JobAwareTopicInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A topic to initiate renewal of payment transactions for uncaptured payments that are about to expire.
 */
class ReAuthorizePaymentTransactionsInitTopic extends AbstractTopic implements JobAwareTopicInterface
{
    #[\Override]
    public static function getName(): string
    {
        return 'oro.stripe_payment.re_authorize_payment_transactions.init';
    }

    #[\Override]
    public static function getDescription(): string
    {
        return 'Initiates renewal of payment transactions for uncaptured payments that are about to expire.';
    }

    #[\Override]
    public function configureMessageBody(OptionsResolver $resolver): void
    {
    }

    #[\Override]
    public function createJobName($messageBody): string
    {
        return self::getName();
    }
}
