<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

/**
 * Interface for Request objects to prepare data for API requests.
 */
interface StripeApiRequestInterface
{
    /**
     * Prepare request data for API requests to STRIPE service.
     */
    public function getRequestData(): array;

    /**
     * Extract saved payment intent parameter.
     */
    public function getPaymentId(): ?string;
}
