<?php

namespace Oro\Bundle\StripePaymentBundle\Form\Extension;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Form\Type\WorkflowTransitionType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Adds "additional_data" field to the specified checkout step of the specified checkout workflow.
 */
class AdditionalDataOnCheckoutStepExtension extends AbstractTypeExtension
{
    /**
     * @param string $applicableWorkflow The name of the workflow, e.g. "b2b_flow_checkout".
     * @param string $applicableStep The name of the step, e.g. "order_review".
     */
    public function __construct(
        private readonly string $applicableWorkflow,
        private readonly string $applicableStep
    ) {
    }

    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        return [WorkflowTransitionType::class];
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$this->isApplicable($options)) {
            return;
        }

        if (!$builder->has('additional_data')) {
            $builder->add('additional_data', HiddenType::class);
        }
    }

    private function isApplicable(array $options): bool
    {
        return isset($options['workflow_item']) &&
            $options['workflow_item'] instanceof WorkflowItem &&
            $options['workflow_item']->getWorkflowName() === $this->applicableWorkflow &&
            $options['workflow_item']->getCurrentStep()?->getName() === $this->applicableStep;
    }
}
