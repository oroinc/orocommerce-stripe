<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Used for unsupported events handling
 */
class UnsupportedResponse extends AbstractResponseObject implements ResponseObjectInterface
{
    #[\Override]
    public function getStatus(): string
    {
        return 'failed';
    }

    #[\Override]
    public function getIdentifier(): string
    {
        return 'null';
    }

    #[\Override]
    public function getData(): array
    {
        return [];
    }
}
