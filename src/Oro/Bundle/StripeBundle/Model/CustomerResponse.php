<?php

namespace Oro\Bundle\StripeBundle\Model;

use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;

/**
 * Stores data for customer object responses.
 */
class CustomerResponse extends AbstractResponseObject implements ResponseObjectInterface
{
    public const CUSTOMER_ID_PARAM = 'customerId';

    public function getStatus(): string
    {
        return $this->getValue('created') ? StripeApiResponseInterface::SUCCESS_STATUS : 'failed';
    }

    public function getIdentifier(): string
    {
        return $this->getValue('id');
    }

    public function getData(): array
    {
        return [
            'data' => [
                self::CUSTOMER_ID_PARAM => $this->getIdentifier()
            ]
        ];
    }
}
