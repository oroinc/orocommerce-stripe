<?php

namespace Oro\Bundle\StripeBundle\Client;

use Oro\Bundle\StripeBundle\Client\Request\StripeApiRequestInterface;
use Oro\Bundle\StripeBundle\Model\CollectionResponseInterface;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Abstraction for stripe payment gateway. Provide basic methods for interaction with Stripe payment service.
 */
interface StripeGatewayInterface
{
    public function purchase(StripeApiRequestInterface $request): ResponseObjectInterface;

    public function confirm(StripeApiRequestInterface $request): ResponseObjectInterface;

    public function capture(StripeApiRequestInterface $request): ResponseObjectInterface;

    public function createCustomer(StripeApiRequestInterface $request): ResponseObjectInterface;

    public function createSetupIntent(StripeApiRequestInterface $request): ResponseObjectInterface;

    public function cancel(StripeApiRequestInterface $request): ResponseObjectInterface;

    public function refund(StripeApiRequestInterface $request): ResponseObjectInterface;

    public function findSetupIntentCustomer(string $setupIntentId): ResponseObjectInterface;

    public function findSetupIntent(string $setupIntentId): ResponseObjectInterface;

    public function getAllRefunds(array $criteria): CollectionResponseInterface;
}
