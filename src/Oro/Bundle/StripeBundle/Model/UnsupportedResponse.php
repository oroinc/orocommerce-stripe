<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Used for unsupported events handling
 */
class UnsupportedResponse extends AbstractResponseObject implements ResponseObjectInterface
{
    public function getStatus(): string
    {
        return 'failed';
    }

    public function getIdentifier(): string
    {
        return 'null';
    }

    public function getData(): array
    {
        return [];
    }
}
