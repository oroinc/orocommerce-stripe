<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Collection storage for refund data responses.
 */
class RefundsCollectionResponse extends AbstractCollectionResponse
{
    protected function createItem(array $itemData)
    {
        return new RefundResponse($itemData);
    }
}
