<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Request;

use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\CreateCustomerRequest;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CreateCustomerRequestTest extends TestCase
{
    use EntityTrait;

    private DoctrineHelper&MockObject $doctrineHelper;
    private EntityNameResolver&MockObject $entityNameResolver;
    private PaymentTransaction $paymentTransaction;
    private CreateCustomerRequest $request;

    #[\Override]
    protected function setUp(): void
    {
        $this->paymentTransaction = new PaymentTransaction();
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->entityNameResolver = $this->createMock(EntityNameResolver::class);

        $this->request = new CreateCustomerRequest(
            $this->paymentTransaction,
            $this->doctrineHelper,
            $this->entityNameResolver
        );
    }

    public function testGetRequestData(): void
    {
        $user = $this->getEntity(CustomerUser::class, ['id' => 10]);
        $country = new Country('US');
        $address = new OrderAddress();
        $address->setCity('City')
            ->setCountry($country)
            ->setRegionText('State')
            ->setPostalCode(90000)
            ->setStreet('ALine1');
        $user->setEmail('test@test.com');
        $order = $this->getEntity(Order::class, ['id' => 100]);
        $order->setBillingAddress($address);

        $this->paymentTransaction->setEntityClass(Order::class);
        $this->paymentTransaction->setEntityIdentifier(100);
        $this->paymentTransaction->setFrontendOwner($user);
        $this->paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                'stripePaymentMethodId' => 'pm1'
            ])
        ]);

        $this->doctrineHelper->expects($this->once())
            ->method('getEntityReference')
            ->willReturn($order);

        $this->entityNameResolver->expects($this->once())
            ->method('getName')
            ->with($user)
            ->willReturn('Test User');

        $expected = [
            'payment_method' => 'pm1',
            'email' => 'test@test.com',
            'name' => 'Test User',
            'address' => [
                'city' => 'City',
                'country' => 'US',
                'line1' => 'ALine1',
                'postal_code' => 90000,
                'state' => 'State'
            ]
        ];

        $this->assertEquals($expected, $this->request->getRequestData());
    }

    public function testGetPaymentId(): void
    {
        $this->assertNull($this->request->getPaymentId());
    }
}
