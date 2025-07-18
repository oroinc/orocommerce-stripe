<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization\Provider;

/**
 * Provides the IDs of authorization payment transactions that are about to expire by delegating calls
 * to inner providers.
 */
class ReAuthorizePaymentTransactionsProviderComposite implements ReAuthorizePaymentTransactionsProviderInterface
{
    /**
     * @param iterable<ReAuthorizePaymentTransactionsProviderInterface> $innerProviders
     */
    public function __construct(
        private readonly iterable $innerProviders
    ) {
    }

    #[\Override]
    public function getPaymentTransactionIds(): iterable
    {
        foreach ($this->innerProviders as $provider) {
            foreach ($provider->getPaymentTransactionIds() as $id) {
                yield $id;
            }
        }
    }
}
