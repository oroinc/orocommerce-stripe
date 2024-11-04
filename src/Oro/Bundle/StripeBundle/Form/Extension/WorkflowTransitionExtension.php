<?php

namespace Oro\Bundle\StripeBundle\Form\Extension;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Form\Type\WorkflowTransitionType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Adds the field 'additional_data' on the 'order_review' workflow step
 * This field is used by Apple/Google Pay payment method to pass
 * payment method data to the backend
 */
class WorkflowTransitionExtension extends AbstractTypeExtension
{
    protected const APPLICABLE_WORKFLOW = 'b2b_flow_checkout';
    protected const APPLICABLE_STEP = 'order_review';
    protected const ADDITIONAL_DATA_FIELD_NAME = 'additional_data';

    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        return [WorkflowTransitionType::class];
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$this->isApplicable($options)) {
            return;
        }

        if (!$builder->has(static::ADDITIONAL_DATA_FIELD_NAME)) {
            $builder->add(static::ADDITIONAL_DATA_FIELD_NAME, HiddenType::class);
        }
    }

    protected function isApplicable(array $options): bool
    {
        return isset($options['workflow_item']) &&
            $options['workflow_item'] instanceof WorkflowItem &&
            $options['workflow_item']->getWorkflowName() === static::APPLICABLE_WORKFLOW &&
            $options['workflow_item']->getCurrentStep()?->getName() === static::APPLICABLE_STEP;
    }
}
