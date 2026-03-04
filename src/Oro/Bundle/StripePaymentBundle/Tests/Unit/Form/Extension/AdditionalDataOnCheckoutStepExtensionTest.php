<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Form\Extension;

use Oro\Bundle\StripePaymentBundle\Form\Extension\AdditionalDataOnCheckoutStepExtension;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowStep;
use Oro\Bundle\WorkflowBundle\Form\Type\WorkflowTransitionType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

final class AdditionalDataOnCheckoutStepExtensionTest extends TestCase
{
    private const string APPLICABLE_WORKFLOW = 'b2b_flow_checkout';
    private const string APPLICABLE_STEP = 'order_review';
    private const string OTHER_WORKFLOW = 'other_workflow';
    private const string OTHER_STEP = 'other_step';

    private AdditionalDataOnCheckoutStepExtension $extension;
    private FormBuilderInterface&MockObject $formBuilder;

    protected function setUp(): void
    {
        $this->formBuilder = $this->createMock(FormBuilderInterface::class);
        $this->extension = new AdditionalDataOnCheckoutStepExtension(
            self::APPLICABLE_WORKFLOW,
            self::APPLICABLE_STEP
        );
    }

    public function testGetExtendedTypes(): void
    {
        self::assertEquals([WorkflowTransitionType::class], iterator_to_array($this->extension::getExtendedTypes()));
    }

    public function testBuildFormWhenNoWorkflowItemInOptions(): void
    {
        $this->formBuilder
            ->expects(self::never())
            ->method('has');

        $this->formBuilder
            ->expects(self::never())
            ->method('add');

        $this->extension->buildForm($this->formBuilder, []);
    }

    public function testBuildFormWhenWorkflowItemIsNotInstanceOfWorkflowItem(): void
    {
        $this->formBuilder
            ->expects(self::never())
            ->method('has');

        $this->formBuilder
            ->expects(self::never())
            ->method('add');

        $options = [
            'workflow_item' => new \stdClass(),
        ];

        $this->extension->buildForm($this->formBuilder, $options);
    }

    public function testBuildFormWhenWorkflowNameDoesNotMatch(): void
    {
        $workflowItem = $this->createWorkflowItem(self::OTHER_WORKFLOW, self::APPLICABLE_STEP);

        $this->formBuilder
            ->expects(self::never())
            ->method('has');

        $this->formBuilder
            ->expects(self::never())
            ->method('add');

        $options = [
            'workflow_item' => $workflowItem,
        ];

        $this->extension->buildForm($this->formBuilder, $options);
    }

    public function testBuildFormWhenStepNameDoesNotMatch(): void
    {
        $workflowItem = $this->createWorkflowItem(self::APPLICABLE_WORKFLOW, self::OTHER_STEP);

        $this->formBuilder
            ->expects(self::never())
            ->method('has');

        $this->formBuilder
            ->expects(self::never())
            ->method('add');

        $options = [
            'workflow_item' => $workflowItem,
        ];

        $this->extension->buildForm($this->formBuilder, $options);
    }

    public function testBuildFormWhenCurrentStepIsNull(): void
    {
        $workflowItem = new WorkflowItem();
        $workflowItem->setWorkflowName(self::APPLICABLE_WORKFLOW);

        $this->formBuilder
            ->expects(self::never())
            ->method('has');

        $this->formBuilder
            ->expects(self::never())
            ->method('add');

        $options = [
            'workflow_item' => $workflowItem,
        ];

        $this->extension->buildForm($this->formBuilder, $options);
    }

    public function testBuildFormWhenApplicableAndFieldDoesNotExist(): void
    {
        $workflowItem = $this->createWorkflowItem(self::APPLICABLE_WORKFLOW, self::APPLICABLE_STEP);

        $this->formBuilder
            ->expects(self::once())
            ->method('has')
            ->with('additional_data')
            ->willReturn(false);

        $this->formBuilder
            ->expects(self::once())
            ->method('add')
            ->with('additional_data', HiddenType::class);

        $options = [
            'workflow_item' => $workflowItem,
        ];

        $this->extension->buildForm($this->formBuilder, $options);
    }

    public function testBuildFormWhenApplicableAndFieldAlreadyExists(): void
    {
        $workflowItem = $this->createWorkflowItem(self::APPLICABLE_WORKFLOW, self::APPLICABLE_STEP);

        $this->formBuilder
            ->expects(self::once())
            ->method('has')
            ->with('additional_data')
            ->willReturn(true);

        $this->formBuilder
            ->expects(self::never())
            ->method('add');

        $options = [
            'workflow_item' => $workflowItem,
        ];

        $this->extension->buildForm($this->formBuilder, $options);
    }

    private function createWorkflowItem(string $workflowName, string $stepName): WorkflowItem
    {
        $workflowStep = new WorkflowStep();
        $workflowStep->setName($stepName);

        $workflowDefinition = new WorkflowDefinition();
        $workflowDefinition->setName($workflowName);

        $workflowItem = new WorkflowItem();
        $workflowItem->setWorkflowName($workflowName);
        $workflowItem->setCurrentStep($workflowStep);
        $workflowItem->setDefinition($workflowDefinition);

        return $workflowItem;
    }
}
