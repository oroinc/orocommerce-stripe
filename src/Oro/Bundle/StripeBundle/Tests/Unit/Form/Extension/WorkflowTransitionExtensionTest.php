<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Form\Extension;

use Oro\Bundle\StripeBundle\Form\Extension\WorkflowTransitionExtension;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowStep;
use Symfony\Component\Form\Test\FormBuilderInterface;

class WorkflowTransitionExtensionTest extends \PHPUnit\Framework\TestCase
{
    private FormBuilderInterface $builder;
    private WorkflowTransitionExtension $formExtension;

    protected function setUp(): void
    {
        $this->builder = $this->createMock(FormBuilderInterface::class);

        $this->formExtension = new WorkflowTransitionExtension();
    }

    /**
     * @dataProvider buildFormProvider
     */
    public function testBuildForm(
        string $workflow,
        ?string $step,
        bool $hasField,
        bool $expectedFieldAdded
    ) {
        $workflowStep = null;
        if ($step) {
            $workflowStep = (new WorkflowStep())
                ->setName($step);
        }

        $workflowItem = (new WorkflowItem())
            ->setWorkflowName($workflow)
            ->setCurrentStep($workflowStep);

        $this->builder->expects($this->any())
            ->method('has')
            ->with('additional_data')
            ->willReturn($hasField);

        if ($expectedFieldAdded) {
            $this->builder->expects($this->once())
                ->method('add')
                ->with('additional_data');
        } else {
            $this->builder->expects($this->never())
                ->method('add')
                ->with('additional_data');
        }

        $this->formExtension->buildForm($this->builder, [
            'workflow_item' => $workflowItem
        ]);
    }

    public function buildFormProvider(): array
    {
        return [
            "applicable, field doesn't exist" => [
                'workflow' => 'b2b_flow_checkout',
                'step' => 'order_review',
                'hasField' => false,
                'expectedFieldAdded' => true,
            ],
            "not applicable step, field doesn't exist" => [
                'workflow' => 'b2b_flow_checkout',
                'step' => 'payment_method',
                'hasField' => false,
                'expectedFieldAdded' => false,
            ],
            "not applicable workflow, field doesn't exist" => [
                'workflow' => 'alternative_flow_checkout',
                'step' => 'order_review',
                'hasField' => false,
                'expectedFieldAdded' => false,
            ],
            "no workflow step, field doesn't exist" => [
                'workflow' => 'alternative_flow_checkout',
                'step' => null,
                'hasField' => false,
                'expectedFieldAdded' => false,
            ],
            "applicable, field exists" => [
                'workflow' => 'b2b_flow_checkout',
                'step' => 'order_review',
                'hasField' => true,
                'expectedFieldAdded' => false,
            ],
        ];
    }
}
