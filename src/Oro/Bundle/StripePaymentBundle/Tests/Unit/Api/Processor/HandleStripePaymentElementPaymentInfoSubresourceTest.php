<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Api\Processor;

use Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\ChangeSubresourceProcessorTestCase;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\StripePaymentBundle\Api\Model\StripePaymentElementPaymentInfoRequest;
use Oro\Bundle\StripePaymentBundle\Api\Processor\HandleStripePaymentElementPaymentInfoSubresource;

final class HandleStripePaymentElementPaymentInfoSubresourceTest extends ChangeSubresourceProcessorTestCase
{
    private HandleStripePaymentElementPaymentInfoSubresource $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new HandleStripePaymentElementPaymentInfoSubresource();
    }

    public function testProcess(): void
    {
        $confirmationTokenId = 'test_token';
        $paymentMethodType = 'test_type';

        $request = new StripePaymentElementPaymentInfoRequest();
        $request->setConfirmationTokenId($confirmationTokenId);
        $request->setPaymentMethodType($paymentMethodType);

        $checkout = new Checkout();
        $subresourceName = 'paymentInfoStripePaymentElement';

        $this->context->setParentEntity($checkout);
        $this->context->setAssociationName($subresourceName);
        $this->context->setResult([$subresourceName => $request]);

        $this->processor->process($this->context);

        self::assertJsonStringEqualsJsonString(
            \json_encode([
                'confirmationToken' => [
                    'id' => $confirmationTokenId,
                    'paymentMethodPreview' => ['type' => $paymentMethodType],
                ],
            ], JSON_THROW_ON_ERROR),
            $checkout->getAdditionalData()
        );
        self::assertSame($checkout, $this->context->getResult());
    }
}
