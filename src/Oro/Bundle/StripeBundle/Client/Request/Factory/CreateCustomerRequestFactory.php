<?php

namespace Oro\Bundle\StripeBundle\Client\Request\Factory;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\CreateCustomerRequest;

/**
 * Creates instance of CreateCustomerRequest.
 */
class CreateCustomerRequestFactory
{
    private DoctrineHelper $doctrineHelper;
    private EntityNameResolver $entityNameResolver;

    public function __construct(
        DoctrineHelper $doctrineHelper,
        EntityNameResolver $entityNameResolver
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->entityNameResolver = $entityNameResolver;
    }

    public function create(PaymentTransaction $paymentTransaction): CreateCustomerRequest
    {
        return new CreateCustomerRequest(
            $paymentTransaction,
            $this->doctrineHelper,
            $this->entityNameResolver
        );
    }
}
