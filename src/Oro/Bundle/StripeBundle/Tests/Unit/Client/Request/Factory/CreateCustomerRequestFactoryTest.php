<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Request\Factory;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\CreateCustomerRequest;
use Oro\Bundle\StripeBundle\Client\Request\Factory\CreateCustomerRequestFactory;
use PHPUnit\Framework\TestCase;

class CreateCustomerRequestFactoryTest extends TestCase
{
    public function testCreate()
    {
        $doctrineHelper = $this->createMock(DoctrineHelper::class);
        $entityNameResolver = $this->createMock(EntityNameResolver::class);

        $factory = new CreateCustomerRequestFactory(
            $doctrineHelper,
            $entityNameResolver
        );

        $paymentTransaction = new PaymentTransaction();
        $request = $factory->create($paymentTransaction);
        $this->assertInstanceOf(CreateCustomerRequest::class, $request);
    }
}
