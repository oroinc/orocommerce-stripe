<?php

namespace Oro\Bundle\StripePaymentBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Form\Extension\ValidationExtension;
use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeSubresourceContext;
use Oro\Bundle\StripePaymentBundle\Api\Model\StripePaymentElementPaymentInfoRequest;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Prepares the form data for the checkout Stripe Payment Element payment information sub-resource.
 */
class PrepareStripePaymentElementPaymentInfoSubresourceFormData implements ProcessorInterface
{
    #[\Override]
    public function process(ContextInterface $context): void
    {
        /** @var ChangeSubresourceContext $context */

        if ($context->hasResult()) {
            return;
        }

        $associationName = $context->getAssociationName();
        $context->setRequestData([$associationName => $context->getRequestData()]);
        $context->setResult([$associationName => new StripePaymentElementPaymentInfoRequest()]);

        $formOptions = $context->getFormOptions() ?? [];
        $formOptions[ValidationExtension::ENABLE_FULL_VALIDATION] = true;
        $context->setFormOptions($formOptions);
    }
}
