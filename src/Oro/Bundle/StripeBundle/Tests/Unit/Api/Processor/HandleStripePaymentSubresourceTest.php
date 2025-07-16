<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Api\Processor;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\ActionBundle\Model\ActionExecutor;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Model\ErrorMetaProperty;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\FlushDataHandlerContext;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\FlushDataHandlerInterface;
use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeSubresourceContext;
use Oro\Bundle\ApiBundle\Processor\Subresource\Shared\SaveParentEntity;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\ChangeSubresourceProcessorTestCase;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Provider\MultiShipping\GroupedCheckoutLineItemsProvider;
use Oro\Bundle\CheckoutBundle\Workflow\ActionGroup\AddressActionsInterface;
use Oro\Bundle\CheckoutBundle\Workflow\ActionGroup\CheckoutActionsInterface;
use Oro\Bundle\CheckoutBundle\Workflow\ActionGroup\SplitOrderActionsInterface;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Provider\PaymentStatusProvider;
use Oro\Bundle\PaymentBundle\Provider\PaymentStatusProviderInterface;
use Oro\Bundle\StripeBundle\Api\Model\StripePaymentRequest;
use Oro\Bundle\StripeBundle\Api\Processor\HandleStripePaymentSubresource;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\PropertyAccess\PropertyPath;

class HandleStripePaymentSubresourceTest extends ChangeSubresourceProcessorTestCase
{
    private SplitOrderActionsInterface&MockObject $splitOrderActions;
    private CheckoutActionsInterface&MockObject $checkoutActions;
    private AddressActionsInterface&MockObject $addressActions;
    private ActionExecutor&MockObject $actionExecutor;
    private PaymentStatusProviderInterface&MockObject $paymentStatusProvider;
    private GroupedCheckoutLineItemsProvider&MockObject $groupedCheckoutLineItemsProvider;
    private DoctrineHelper&MockObject $doctrineHelper;
    private FlushDataHandlerInterface&MockObject $flushDataHandler;
    private HandleStripePaymentSubresource $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->splitOrderActions = $this->createMock(SplitOrderActionsInterface::class);
        $this->checkoutActions = $this->createMock(CheckoutActionsInterface::class);
        $this->addressActions = $this->createMock(AddressActionsInterface::class);
        $this->actionExecutor = $this->createMock(ActionExecutor::class);
        $this->paymentStatusProvider = $this->createMock(PaymentStatusProviderInterface::class);
        $this->groupedCheckoutLineItemsProvider = $this->createMock(GroupedCheckoutLineItemsProvider::class);
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->flushDataHandler = $this->createMock(FlushDataHandlerInterface::class);

        $this->processor = new HandleStripePaymentSubresource(
            $this->splitOrderActions,
            $this->checkoutActions,
            $this->addressActions,
            $this->actionExecutor,
            $this->paymentStatusProvider,
            $this->groupedCheckoutLineItemsProvider,
            $this->doctrineHelper,
            $this->flushDataHandler
        );
    }

    private function expectSaveChanges(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManagerForClass')
            ->with(Checkout::class)
            ->willReturn($em);
        $this->flushDataHandler->expects(self::once())
            ->method('flushData')
            ->with(self::identicalTo($em), self::isInstanceOf(FlushDataHandlerContext::class))
            ->willReturnCallback(function (EntityManagerInterface $entityManager, FlushDataHandlerContext $context) {
                /** @var ChangeSubresourceContext $entityContext */
                $entityContext = $context->getEntityContexts()[0];
                self::assertCount(0, $entityContext->getAdditionalEntityCollection()->getEntities());
            });
    }

    private function expectSaveChangesAndRemoveOrder(Order $order): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManagerForClass')
            ->with(Checkout::class)
            ->willReturn($em);
        $this->flushDataHandler->expects(self::once())
            ->method('flushData')
            ->with(self::identicalTo($em), self::isInstanceOf(FlushDataHandlerContext::class))
            ->willReturnCallback(function (
                EntityManagerInterface $entityManager,
                FlushDataHandlerContext $context
            ) use ($order) {
                /** @var ChangeSubresourceContext $entityContext */
                $entityContext = $context->getEntityContexts()[0];
                self::assertCount(1, $entityContext->getAdditionalEntityCollection()->getEntities());
                self::assertTrue($entityContext->getAdditionalEntityCollection()->shouldEntityBeRemoved($order));
            });
    }

    public function testProcessPaymentInProgressWithoutOrder(): void
    {
        $checkout = new Checkout();
        $checkout->setAdditionalData(json_encode(['stripePaymentMethodId' => 'id_001'], JSON_THROW_ON_ERROR));

        $checkout->setPaymentMethod('stripe');
        $checkout->setPaymentInProgress(true);

        $this->groupedCheckoutLineItemsProvider->expects(self::never())
            ->method('getGroupedLineItemsIds');

        $this->paymentStatusProvider->expects(self::never())
            ->method('getPaymentStatus');

        $this->context->setParentEntity($checkout);
        $this->context->setAssociationName('test');
        $this->processor->process($this->context);

        self::assertTrue($checkout->isPaymentInProgress());
        self::assertTrue($this->context->hasErrors());
        self::assertEquals(
            [
                Error::createValidationError(
                    'payment constraint',
                    'Can not process payment without order.'
                )
            ],
            $this->context->getErrors()
        );
        self::assertFalse($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }

    public function testProcessPaymentInProgressWithNotFinishedStatus(): void
    {
        $checkout = new Checkout();
        $checkout->setAdditionalData(json_encode(['stripePaymentMethodId' => 'id_001'], JSON_THROW_ON_ERROR));
        $order = new Order();

        $checkout->setPaymentMethod('stripe');
        $checkout->setOrder($order);
        $checkout->setPaymentInProgress(true);

        $this->groupedCheckoutLineItemsProvider->expects(self::never())
            ->method('getGroupedLineItemsIds');

        $this->paymentStatusProvider->expects(self::once())
            ->method('getPaymentStatus')
            ->with($order)
            ->willReturn(PaymentStatusProvider::CANCELED);

        $this->expectSaveChangesAndRemoveOrder($order);

        $this->context->setParentEntity($checkout);
        $this->context->setAssociationName('test');
        $this->processor->process($this->context);

        self::assertFalse($checkout->isPaymentInProgress());
        self::assertTrue($this->context->hasErrors());
        self::assertEquals(
            [
                Error::createValidationError(
                    'payment constraint',
                    'Payment failed, please try again or select a different payment method.'
                )
            ],
            $this->context->getErrors()
        );
        self::assertTrue($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }

    public function testProcessPaymentInProgress(): void
    {
        $checkout = new Checkout();
        $checkout->setAdditionalData(json_encode(['stripePaymentMethodId' => 'id_001'], JSON_THROW_ON_ERROR));
        $order = new Order();

        $checkout->setPaymentMethod('stripe');
        $checkout->setOrder($order);
        $checkout->setPaymentInProgress(true);

        $this->groupedCheckoutLineItemsProvider->expects(self::never())
            ->method('getGroupedLineItemsIds');

        $this->paymentStatusProvider->expects(self::once())
            ->method('getPaymentStatus')
            ->with($order)
            ->willReturn(PaymentStatusProvider::FULL);

        $this->addressActions->expects(self::once())
            ->method('actualizeAddresses')
            ->with($checkout, $order);
        $this->checkoutActions->expects(self::once())
            ->method('fillCheckoutCompletedData')
            ->with($checkout, $order);

        $this->expectSaveChanges();

        $this->context->setParentEntity($checkout);
        $this->context->setAssociationName('test');
        $this->context->setResult($order);
        $this->processor->process($this->context);

        self::assertFalse($checkout->isPaymentInProgress());
        self::assertFalse($this->context->hasErrors());
        self::assertTrue($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }

    public function testProcessExecutePurchaseWithRequiresActionError(): void
    {
        $checkout = new Checkout();
        $checkout->setAdditionalData(json_encode(['stripePaymentMethodId' => 'id_001'], JSON_THROW_ON_ERROR));
        $checkout->setPaymentMethod('stripe');
        $order = new Order();
        $order->setTotal(100.0);
        $order->setCurrency('USD');
        $checkout->setOrder($order);
        $groupedLineItemIds = ['group1' => ['item1']];

        $request = new StripePaymentRequest();
        $request->setFailureUrl('failureUrl');
        $request->setSuccessUrl('successUrl');
        $request->setPartiallyPaidUrl('partiallyPaid');

        $this->groupedCheckoutLineItemsProvider->expects(self::once())
            ->method('getGroupedLineItemsIds')
            ->with($checkout)
            ->willReturn($groupedLineItemIds);
        $this->splitOrderActions->expects(self::once())
            ->method('placeOrder')
            ->with($checkout, $groupedLineItemIds)
            ->willReturn($order);
        $this->actionExecutor->expects(self::once())
            ->method('executeAction')
            ->with(
                'payment_purchase',
                [
                    'attribute' => new PropertyPath('response'),
                    'object' => $order,
                    'amount' => 100.0,
                    'currency' => 'USD',
                    'paymentMethod' => 'stripe',
                    'transactionOptions' => [
                        'failureUrl' => 'failureUrl',
                        'successUrl' => 'successUrl',
                        'partiallyPaidUrl' => 'partiallyPaid',
                        'additionalData' => '{"stripePaymentMethodId":"id_001"}'
                    ]
                ]
            )
            ->willReturn([
                'response' => [
                    'successful' => false,
                    'requires_action' => true
                ]
            ]);

        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManagerForClass');

        $this->context->setParentEntity($checkout);
        $this->context->setAssociationName('test');
        $this->context->setResult(['test' => $request]);
        $this->processor->process($this->context);

        self::assertTrue($checkout->isPaymentInProgress());
        self::assertEquals($order, $checkout->getOrder());
        self::assertTrue($this->context->hasErrors());
        $error = Error::createValidationError(
            'payment action constraint',
            'The payment requires additional actions.'
        );
        $error->addMetaProperty(
            'data',
            new ErrorMetaProperty(['successful' => false, 'requires_action' => true], 'array')
        );
        self::assertEquals([$error], $this->context->getErrors());
        self::assertFalse($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }

    public function testProcessExecutePurchaseWithNotRequiresActionError(): void
    {
        $checkout = new Checkout();
        $checkout->setAdditionalData(json_encode(['stripePaymentMethodId' => 'id_001'], JSON_THROW_ON_ERROR));
        $checkout->setPaymentMethod('stripe');
        $order = new Order();
        $order->setTotal(100.0);
        $order->setCurrency('USD');
        $checkout->setOrder($order);
        $groupedLineItemIds = ['group1' => ['item1']];

        $request = new StripePaymentRequest();
        $request->setFailureUrl('failureUrl');
        $request->setSuccessUrl('successUrl');
        $request->setPartiallyPaidUrl('partiallyPaid');

        $this->groupedCheckoutLineItemsProvider->expects(self::once())
            ->method('getGroupedLineItemsIds')
            ->with($checkout)
            ->willReturn($groupedLineItemIds);
        $this->splitOrderActions->expects(self::once())
            ->method('placeOrder')
            ->with($checkout, $groupedLineItemIds)
            ->willReturn($order);
        $this->actionExecutor->expects(self::once())
            ->method('executeAction')
            ->with(
                'payment_purchase',
                [
                    'attribute' => new PropertyPath('response'),
                    'object' => $order,
                    'amount' => 100.0,
                    'currency' => 'USD',
                    'paymentMethod' => 'stripe',
                    'transactionOptions' => [
                        'failureUrl' => 'failureUrl',
                        'successUrl' => 'successUrl',
                        'partiallyPaidUrl' => 'partiallyPaid',
                        'additionalData' => '{"stripePaymentMethodId":"id_001"}'
                    ]
                ]
            )
            ->willReturn([
                'response' => [
                    'successful' => false,
                    'error' => 'Some error'
                ]
            ]);

        $this->expectSaveChangesAndRemoveOrder($order);

        $this->context->setParentEntity($checkout);
        $this->context->setAssociationName('test');
        $this->context->setResult(['test' => $request]);
        $this->processor->process($this->context);

        self::assertFalse($checkout->isPaymentInProgress());
        self::assertNull($checkout->getOrder());
        self::assertTrue($this->context->hasErrors());
        self::assertEquals(
            [
                Error::createValidationError(
                    'payment constraint',
                    'Some error. Stripe Error Code: "", Decline code: ""'
                )
            ],
            $this->context->getErrors()
        );
        self::assertTrue($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }

    public function testProcessExecutePurchase(): void
    {
        $checkout = new Checkout();
        $checkout->setAdditionalData(json_encode(['stripePaymentMethodId' => 'id_001'], JSON_THROW_ON_ERROR));
        $checkout->setPaymentMethod('stripe');
        $order = new Order();
        $order->setTotal(100.0);
        $order->setCurrency('USD');
        $checkout->setOrder($order);
        $groupedLineItemIds = ['group1' => ['item1']];

        $request = new StripePaymentRequest();
        $request->setFailureUrl('failureUrl');
        $request->setSuccessUrl('successUrl');
        $request->setPartiallyPaidUrl('partiallyPaid');

        $this->groupedCheckoutLineItemsProvider->expects(self::once())
            ->method('getGroupedLineItemsIds')
            ->with($checkout)
            ->willReturn($groupedLineItemIds);
        $this->splitOrderActions->expects(self::once())
            ->method('placeOrder')
            ->with($checkout, $groupedLineItemIds)
            ->willReturn($order);
        $this->actionExecutor->expects(self::once())
            ->method('executeAction')
            ->with(
                'payment_purchase',
                [
                    'attribute' => new PropertyPath('response'),
                    'object' => $order,
                    'amount' => 100.0,
                    'currency' => 'USD',
                    'paymentMethod' => 'stripe',
                    'transactionOptions' => [
                        'failureUrl' => 'failureUrl',
                        'successUrl' => 'successUrl',
                        'partiallyPaidUrl' => 'partiallyPaid',
                        'additionalData' => '{"stripePaymentMethodId":"id_001"}'
                    ]
                ]
            )
            ->willReturn(['response' => ['successful' => true]]);

        $this->addressActions->expects(self::once())
            ->method('actualizeAddresses')
            ->with($checkout, $order);
        $this->checkoutActions->expects(self::once())
            ->method('fillCheckoutCompletedData')
            ->with($checkout, $order);

        $this->expectSaveChanges();

        $this->context->setParentEntity($checkout);
        $this->context->setAssociationName('test');
        $this->context->setResult(['test' => $request]);
        $this->processor->process($this->context);

        self::assertFalse($checkout->isPaymentInProgress());
        self::assertEquals($order, $checkout->getOrder());
        self::assertFalse($this->context->hasErrors());
        self::assertTrue($this->context->isProcessed(SaveParentEntity::OPERATION_NAME));
    }
}
