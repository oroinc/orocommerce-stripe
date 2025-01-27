<?php

namespace Oro\Bundle\StripeBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Form\Extension\ValidationExtension;
use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeSubresourceContext;
use Oro\Bundle\StripeBundle\Api\Model\StripePaymentInfoRequest;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Prepares the form data for the checkout Stripe payment information sub-resource.
 */
class PrepareStripePaymentInfoSubresourceFormData implements ProcessorInterface
{
    #[\Override]
    public function process(ContextInterface $context): void
    {
        /** @var ChangeSubresourceContext $context */

        if ($context->hasResult()) {
            // the form data are already prepared
            return;
        }

        $associationName = $context->getAssociationName();
        $context->setRequestData([$associationName => $context->getRequestData()]);
        $context->setResult([$associationName => new StripePaymentInfoRequest()]);

        $formOptions = $context->getFormOptions() ?? [];
        $formOptions[ValidationExtension::ENABLE_FULL_VALIDATION] = true;
        $context->setFormOptions($formOptions);
    }
}
