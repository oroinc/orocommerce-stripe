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
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Response object identifier. Could be used as payment transaction reference.
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * Extract response details which could be saved as response in payment transaction.
     *
     * @return array
     */
    public function getData(): array;
}
