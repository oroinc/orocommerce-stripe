<?php

namespace Oro\Bundle\StripeBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeSubresourceContext;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\StripeBundle\Api\Model\StripePaymentInfoRequest;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Handles the checkout Stripe payment information sub-resource.
 */
class HandleStripePaymentInfoSubresource implements ProcessorInterface
{
    #[\Override]
    public function process(ContextInterface $context): void
    {
        /** @var ChangeSubresourceContext $context */

        /** @var StripePaymentInfoRequest $request */
        $request = $context->getResult()[$context->getAssociationName()];

        /** @var Checkout $checkout */
        $checkout = $context->getParentEntity();
        $checkout->setAdditionalData(
            json_encode(['stripePaymentMethodId' => $request->getStripePaymentMethodId()])
        );
        $context->setResult($checkout);
    }
}
