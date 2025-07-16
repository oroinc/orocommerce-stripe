<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Api\Processor;

use Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\ChangeSubresourceProcessorTestCase;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\StripeBundle\Api\Model\StripePaymentInfoRequest;
use Oro\Bundle\StripeBundle\Api\Processor\HandleStripePaymentInfoSubresource;

class HandleStripePaymentInfoSubresourceTest extends ChangeSubresourceProcessorTestCase
{
    private HandleStripePaymentInfoSubresource $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new HandleStripePaymentInfoSubresource();
    }

    public function testProcessSetsStripePaymentMethodId(): void
    {
        $stripePaymentMethodId = 'pm_test';
        $stripePaymentInfoRequest = new StripePaymentInfoRequest();
        $stripePaymentInfoRequest->setStripePaymentMethodId($stripePaymentMethodId);

        $checkout = new Checkout();
        $subresourceName = 'stripePaymentInfo';

        $this->context->setParentEntity($checkout);
        $this->context->setAssociationName($subresourceName);
        $this->context->setResult([$subresourceName => $stripePaymentInfoRequest]);
        $this->processor->process($this->context);

        self::assertJsonStringEqualsJsonString(
            json_encode(['stripePaymentMethodId' => $stripePaymentMethodId], JSON_THROW_ON_ERROR),
            $checkout->getAdditionalData()
        );
        self::assertSame($checkout, $this->context->getResult());
    }
}
