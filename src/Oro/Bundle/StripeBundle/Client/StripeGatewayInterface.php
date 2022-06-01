<?php

namespace Oro\Bundle\StripeBundle\Client;

use Oro\Bundle\StripeBundle\Client\Request\StripeApiRequestInterface;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Abstraction for stripe payment gateway. Provide basic methods for interaction with Stripe payment service.
 */
interface StripeGatewayInterface
{
    public function purchase(StripeApiRequestInterface $request): ResponseObjectInterface;

    public function confirm(StripeApiRequestInterface $request): ResponseObjectInterface;

    public function capture(StripeApiRequestInterface $request): ResponseObjectInterface;
}
