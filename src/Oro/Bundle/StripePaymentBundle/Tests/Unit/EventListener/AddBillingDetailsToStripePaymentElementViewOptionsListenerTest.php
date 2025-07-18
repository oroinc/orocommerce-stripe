<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\EventListener;

use Oro\Bundle\AddressBundle\Entity\Address;
use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\AddressBundle\Entity\Region;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Bundle\PaymentBundle\Context\PaymentContext;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentElementViewOptionsEvent;
use Oro\Bundle\StripePaymentBundle\EventListener\AddBillingDetailsToStripePaymentElementViewOptionsListener;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\View\StripePaymentElementMethodView;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddBillingDetailsToStripePaymentElementViewOptionsListenerTest extends TestCase
{
    private AddBillingDetailsToStripePaymentElementViewOptionsListener $listener;

    private MockObject&TokenAccessorInterface $tokenAccessor;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private MockObject&EntityNameResolver $entityNameResolver;

    protected function setUp(): void
    {
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $this->entityNameResolver = $this->createMock(EntityNameResolver::class);
        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);

        $this->listener = new AddBillingDetailsToStripePaymentElementViewOptionsListener(
            $this->tokenAccessor,
            $this->entityNameResolver
        );
    }

    public function testOnStripePaymentElementViewOptionsWithNonCustomerUser(): void
    {
        $event = $this->createMock(StripePaymentElementViewOptionsEvent::class);

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getUser')
            ->willReturn(new \stdClass());

        $event
            ->expects(self::never())
            ->method('getViewOption');

        $event
            ->expects(self::never())
            ->method('addViewOption');

        $this->listener->onStripePaymentElementViewOptions($event);
    }

    public function testOnStripePaymentElementViewOptionsWithCustomerUser(): void
    {
        $customerUser = (new CustomerUser())
            ->setEmail('john.doe@example.com');

        $billingAddress = (new OrderAddress())
            ->setPhone('+1234567890')
            ->setStreet('123 Main St')
            ->setStreet2('Apt 4B')
            ->setCity('New York')
            ->setRegion((new Region('NY'))->setName('New York'))
            ->setCountry(new Country('US'))
            ->setPostalCode('10001');

        $paymentContext = new PaymentContext([
            PaymentContext::FIELD_CUSTOMER_USER => $customerUser,
            PaymentContext::FIELD_BILLING_ADDRESS => $billingAddress,
        ]);

        $event = new StripePaymentElementViewOptionsEvent(
            $paymentContext,
            $this->stripePaymentElementConfig,
            [StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => []]
        );

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getUser')
            ->willReturn($customerUser);

        $customerUserName = 'John Doe';
        $this->entityNameResolver
            ->expects(self::once())
            ->method('getName')
            ->with($customerUser)
            ->willReturn($customerUserName);

        $this->listener->onStripePaymentElementViewOptions($event);

        self::assertEquals(
            [
                StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => [
                    'defaultValues' => [
                        'billingDetails' => [
                            'name' => $customerUserName,
                            'email' => $customerUser->getEmail(),
                            'phone' => $billingAddress->getPhone(),
                            'address' => [
                                'line1' => $billingAddress->getStreet(),
                                'line2' => $billingAddress->getStreet2(),
                                'city' => $billingAddress->getCity(),
                                'state' => $billingAddress->getRegionName(),
                                'country' => $billingAddress->getCountryIso2(),
                                'postal_code' => $billingAddress->getPostalCode(),
                            ],
                        ],
                    ],
                ],
            ],
            $event->getViewOptions()
        );
    }

    public function testOnStripePaymentElementViewOptionsWithMinimalAddress(): void
    {
        $customerUser = (new CustomerUser())
            ->setEmail('john.doe@example.com');

        $billingAddress = (new Address())
            ->setStreet('123 Main St')
            ->setCity('New York')
            ->setRegion((new Region('NY'))->setName('New York'))
            ->setCountry(new Country('US'))
            ->setPostalCode('10001');

        $paymentContext = new PaymentContext([
            PaymentContext::FIELD_CUSTOMER_USER => $customerUser,
            PaymentContext::FIELD_BILLING_ADDRESS => $billingAddress,
        ]);

        $event = new StripePaymentElementViewOptionsEvent(
            $paymentContext,
            $this->stripePaymentElementConfig,
            [StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => []]
        );

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getUser')
            ->willReturn($customerUser);

        $customerUserName = 'John Doe';
        $this->entityNameResolver
            ->expects(self::once())
            ->method('getName')
            ->with($customerUser)
            ->willReturn($customerUserName);

        $this->listener->onStripePaymentElementViewOptions($event);

        self::assertEquals(
            [
                StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => [
                    'defaultValues' => [
                        'billingDetails' => [
                            'name' => $customerUserName,
                            'email' => $customerUser->getEmail(),
                            'address' => [
                                'line1' => $billingAddress->getStreet(),
                                'city' => $billingAddress->getCity(),
                                'state' => $billingAddress->getRegionName(),
                                'country' => $billingAddress->getCountryIso2(),
                                'postal_code' => $billingAddress->getPostalCode(),
                            ],
                        ],
                    ],
                ],
            ],
            $event->getViewOptions()
        );
    }

    public function testOnStripePaymentElementViewOptionsWithoutBillingAddress(): void
    {
        $customerUser = (new CustomerUser())
            ->setEmail('john.doe@example.com');

        $paymentContext = new PaymentContext([
            PaymentContext::FIELD_CUSTOMER_USER => $customerUser,
        ]);

        $event = new StripePaymentElementViewOptionsEvent(
            $paymentContext,
            $this->stripePaymentElementConfig,
            [StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => []]
        );

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getUser')
            ->willReturn($customerUser);

        $customerUserName = 'John Doe';
        $this->entityNameResolver
            ->expects(self::once())
            ->method('getName')
            ->with($customerUser)
            ->willReturn($customerUserName);

        $this->listener->onStripePaymentElementViewOptions($event);

        self::assertEquals(
            [
                StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => [
                    'defaultValues' => [
                        'billingDetails' => [
                            'name' => $customerUserName,
                            'email' => $customerUser->getEmail(),
                        ],
                    ],
                ],
            ],
            $event->getViewOptions()
        );
    }

    public function testOnStripePaymentElementViewOptionsWithExistingOptions(): void
    {
        $customerUser = (new CustomerUser())
            ->setEmail('john.doe@example.com');

        $paymentContext = new PaymentContext([
            PaymentContext::FIELD_CUSTOMER_USER => $customerUser,
        ]);

        $event = new StripePaymentElementViewOptionsEvent(
            $paymentContext,
            $this->stripePaymentElementConfig,
            [
                StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => [
                    'sampleKey' => 'sampleValue',
                    'defaultValues' => [
                        'sampleKey' => 'sampleValue',
                        'billingDetails' => ['sampleKey' => 'sampleValue'],
                    ],
                ],
            ]
        );

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getUser')
            ->willReturn($customerUser);

        $customerUserName = 'John Doe';
        $this->entityNameResolver
            ->expects(self::once())
            ->method('getName')
            ->with($customerUser)
            ->willReturn($customerUserName);

        $this->listener->onStripePaymentElementViewOptions($event);

        self::assertEquals(
            [
                StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => [
                    'sampleKey' => 'sampleValue',
                    'defaultValues' => [
                        'sampleKey' => 'sampleValue',
                        'billingDetails' => [
                            'sampleKey' => 'sampleValue',
                            'name' => $customerUserName,
                            'email' => $customerUser->getEmail(),
                        ],
                    ],
                ],
            ],
            $event->getViewOptions()
        );
    }
}
