<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization\Provider;

/**
 * Provides the IDs of authorization payment transactions that are about to expire.
 */
interface ReAuthorizePaymentTransactionsProviderInterface
{
    /**
     * @return iterable<int>
     */
    public function getPaymentTransactionIds(): iterable;
}
