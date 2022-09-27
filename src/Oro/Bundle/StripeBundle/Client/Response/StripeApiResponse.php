<?php

namespace Oro\Bundle\StripeBundle\Client\Response;

use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Basic response class. In most cases only 'successful' parameter is enough.
 */
class StripeApiResponse implements StripeApiResponseInterface
{
    private ResponseObjectInterface $responseObject;

    public function __construct(ResponseObjectInterface $responseObject)
    {
        $this->responseObject = $responseObject;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareResponse(): array
    {
        return [
            'successful' => $this->isSuccessful()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isSuccessful(): bool
    {
        return in_array($this->responseObject->getStatus(), [
            self::SUCCESS_STATUS,
            self::REQUIRES_CAPTURE,
            self::CANCELED
        ]);
    }
}
