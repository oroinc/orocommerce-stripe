<?php

namespace Oro\Bundle\StripePaymentBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeSubresourceContext;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\StripePaymentBundle\Api\Model\StripePaymentElementPaymentInfoRequest;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Handles the Stripe Payment Element checkout payment info sub-resource.
 */
class HandleStripePaymentElementPaymentInfoSubresource implements ProcessorInterface
{
    #[\Override]
    public function process(ContextInterface $context): void
    {
        /** @var ChangeSubresourceContext $context */

        /** @var StripePaymentElementPaymentInfoRequest $request */
        $request = $context->getResult()[$context->getAssociationName()];
        /** @var Checkout $checkout */
        $checkout = $context->getParentEntity();

        $checkout->setAdditionalData(
            \json_encode(
                [
                    'confirmationToken' => [
                        'id' => $request->getConfirmationTokenId(),
                        'paymentMethodPreview' => ['type' => $request->getPaymentMethodType()],
                    ],
                ],
                JSON_THROW_ON_ERROR
            )
        );
        $context->setResult($checkout);
    }
}
