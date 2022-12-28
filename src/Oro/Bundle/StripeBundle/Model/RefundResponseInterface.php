<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Interface describes specific methods for Refund Response objects.
 */
interface RefundResponseInterface extends ResponseObjectInterface
{
    public function getRefundedAmount(): float;
}
