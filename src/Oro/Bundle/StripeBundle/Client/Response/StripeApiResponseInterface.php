<?php

namespace Oro\Bundle\StripeBundle\Client\Response;

/**
 * Representation of STRIPE API response.
 */
interface StripeApiResponseInterface
{
    public const SUCCESS_STATUS = 'succeeded';
    public const REQUIRES_CAPTURE = 'requires_capture';
    public const CANCELED = 'canceled';

    /**
     * Prepare response data from STRIPE service
     */
    public function prepareResponse(): array;

    /**
     * Check if payment successful.
     */
    public function isSuccessful(): bool;
}
