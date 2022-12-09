<?php

namespace Oro\Bundle\StripeBundle\Client\Response;

/**
 * Represents cumulative response returned by multiple purchase request.
 */
class MultiPurchaseResponse implements StripeApiResponseInterface
{
    private bool $successful = true;
    private bool $hasSuccessful = false;

    public function prepareResponse(): array
    {
        return [
            'is_multi_transaction' => true,
            'successful' => $this->isSuccessful(),
            'has_successful' => $this->hasSuccessful()
        ];
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function setSuccessful(bool $successful): void
    {
        $this->successful = $successful;
    }

    public function hasSuccessful(): bool
    {
        return $this->hasSuccessful;
    }

    public function setHasSuccessful(bool $hasSuccessful): void
    {
        $this->hasSuccessful = $hasSuccessful;
    }
}
