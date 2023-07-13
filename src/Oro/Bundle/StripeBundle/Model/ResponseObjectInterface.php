<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Basic interface for Stripe response objects.
 */
interface ResponseObjectInterface
{
    public const ACTION_SOURCE_API = 'Stripe API';
    public const ACTION_SOURCE_MANUALLY = 'Manually Stripe Dashboard';

    /**
     * Important information to determine if request was successful. Could be different implementation in different
     * response types.
     */
    public function getStatus(): string;

    /**
     * Response object identifier. Could be used as payment transaction reference.
     */
    public function getIdentifier(): string;

    /**
     * Extract response details which could be saved as response in payment transaction.
     */
    public function getData(): array;
}
