<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor;

use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\Factory\SubOrderPaymentTransactionFactory;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\Provider\SubOrdersByPaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\PurchaseSubOrdersAwareStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent as StripePaymentIntent;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class PurchaseSubOrdersAwareStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const string STRIPE_CUSTOMER_ID = 'cus_123';
    private const string STRIPE_PAYMENT_METHOD_ID = 'pm_123';
    private const string SAMPLE_ACCESS_IDENTIFIER = 'sample_identifier';
    private const string SAMPLE_ACCESS_TOKEN = 'sample_token';

    private PurchaseSubOrdersAwareStripeActionExecutor $executor;

    private MockObject&StripePaymentIntentActionExecutorInterface $stripePaymentIntentActionExecutor;

    private MockObject&SubOrdersByPaymentTransactionProvider $subOrdersByPaymentTransactionProvider;

    private MockObject&SubOrderPaymentTransactionFactory $subOrderPaymentTransactionFactory;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    private StripePaymentElementConfig $stripePaymentElementConfig;

    #[\Override]
    protected function setUp(): void
    {
        $this->stripePaymentIntentActionExecutor = $this->createMock(
            StripePaymentIntentActionExecutorInterface::class
        );
        $this->subOrdersByPaymentTransactionProvider = $this->createMock(
            SubOrdersByPaymentTransactionProvider::class
        );
        $this->subOrderPaymentTransactionFactory = $this->createMock(SubOrderPaymentTransactionFactory::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->executor = new PurchaseSubOrdersAwareStripeActionExecutor(
            $this->stripePaymentIntentActionExecutor,
            $this->subOrdersByPaymentTransactionProvider,
            $this->subOrderPaymentTransactionFactory,
            $this->paymentTransactionProvider
        );

        $this->setUpLoggerMock($this->executor);

        $this->stripePaymentElementConfig = $this->createStripePaymentElementConfig();
    }

    private function createStripePaymentElementConfig(): StripePaymentElementConfig
    {
        return new StripePaymentElementConfig([
            StripePaymentElementConfig::API_VERSION => '2023-10-16',
            StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
            StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
            StripePaymentElementConfig::CAPTURE_METHOD => 'automatic',
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => [],
        ]);
    }

    private function createParentTransaction(string $action = PaymentMethodInterface::PURCHASE): PaymentTransaction
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 100);
        $paymentTransaction->setAction($action);
        $paymentTransaction->setAccessIdentifier(self::SAMPLE_ACCESS_IDENTIFIER);
        $paymentTransaction->setAccessToken(self::SAMPLE_ACCESS_TOKEN);
        $paymentTransaction->setAmount(300.00);
        $paymentTransaction->setCurrency('USD');
        $paymentTransaction->setPaymentMethod('stripe_payment_element');
        $paymentTransaction->setEntityClass(Order::class);
        $paymentTransaction->setEntityIdentifier(1);

        return $paymentTransaction;
    }

    private function createSubOrder(int $id, float $total, string $currency = 'USD'): Order
    {
        $subOrder = new Order();
        ReflectionUtil::setId($subOrder, $id);
        $subOrder->setTotal($total);
        $subOrder->setCurrency($currency);

        return $subOrder;
    }

    private function createSubOrderTransaction(
        Order $subOrder,
        string $action,
        int $id
    ): PaymentTransaction {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, $id);
        $paymentTransaction->setAction($action);
        $paymentTransaction->setAccessIdentifier(self::SAMPLE_ACCESS_IDENTIFIER . '_' . $id);
        $paymentTransaction->setAccessToken(self::SAMPLE_ACCESS_TOKEN . '_' . $id);
        $paymentTransaction->setAmount((string)$subOrder->getTotal());
        $paymentTransaction->setCurrency($subOrder->getCurrency());
        $paymentTransaction->setPaymentMethod('stripe_payment_element');
        $paymentTransaction->setEntityClass(Order::class);
        $paymentTransaction->setEntityIdentifier($subOrder->getId());

        return $paymentTransaction;
    }

    private function createStripePaymentIntent(string $status, string $id = 'pi_123'): StripePaymentIntent
    {
        $stripePaymentIntent = new StripePaymentIntent($id);
        $stripePaymentIntent->status = $status;
        $stripePaymentIntent->payment_method = self::STRIPE_PAYMENT_METHOD_ID;
        $stripePaymentIntent->customer = self::STRIPE_CUSTOMER_ID;

        return $stripePaymentIntent;
    }

    public function testIsSupportedByActionNameReturnsTrueWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::PURCHASE));
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::CHARGE));
        self::assertTrue($this->executor->isSupportedByActionName(PaymentMethodInterface::AUTHORIZE));
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotSupportedAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::CAPTURE));
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::REFUND));
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::VALIDATE));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedActionName(): void
    {
        $parentTransaction = $this->createParentTransaction(PaymentMethodInterface::CAPTURE);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CAPTURE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::never())
            ->method('hasSubOrders');

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoSubOrders(): void
    {
        $parentTransaction = $this->createParentTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('hasSubOrders')
            ->with($parentTransaction)
            ->willReturn(false);

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrueWhenHasSubOrders(): void
    {
        $parentTransaction = $this->createParentTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('hasSubOrders')
            ->with($parentTransaction)
            ->willReturn(true);

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionFailsWhenNoSubOrders(): void
    {
        $parentTransaction = $this->createParentTransaction();

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([]);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Cannot process the payment transaction #{paymentTransactionId}: no sub-orders found',
                [
                    'paymentTransactionId' => $parentTransaction->getId(),
                ]
            );

        $this->stripePaymentIntentActionExecutor
            ->expects(self::never())
            ->method('executeAction');

        $result = $this->executor->executeAction($stripeAction);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testExecuteActionSuccessfullyProcessesSingleSubOrder(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $subOrder = $this->createSubOrder(id: 101, total: 123.45);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder]);

        $subOrderTransaction = $this->createSubOrderTransaction($subOrder, PaymentMethodInterface::PURCHASE, 201);

        $this->subOrderPaymentTransactionFactory
            ->expects(self::once())
            ->method('createSubOrderPaymentTransaction')
            ->with($parentTransaction, $subOrder)
            ->willReturn($subOrderTransaction);

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'succeeded');

        $this->stripePaymentIntentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturnCallback(
                function (StripePaymentIntentAction $action) use ($subOrderTransaction, $stripePaymentIntent) {
                    // Verify setup_future_usage is set
                    self::assertTrue(
                        $subOrderTransaction->getTransactionOption(
                            StripePaymentIntentActionInterface::SETUP_FUTURE_USAGE
                        )
                    );

                    // Simulate successful transaction
                    $subOrderTransaction->setSuccessful(true);
                    $subOrderTransaction->setActive(false);
                    $subOrderTransaction->addTransactionOption(
                        StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                        self::STRIPE_PAYMENT_METHOD_ID
                    );
                    $subOrderTransaction->addTransactionOption(
                        StripePaymentIntentActionInterface::CUSTOMER_ID,
                        self::STRIPE_CUSTOMER_ID
                    );

                    return new StripePaymentIntentActionResult(true, $stripePaymentIntent);
                }
            );

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($subOrderTransaction);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        self::assertSame($stripePaymentIntent, $result->getStripeObject());
        self::assertTrue($parentTransaction->isSuccessful());
        self::assertFalse($parentTransaction->isActive());
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecuteActionSuccessfullyProcessesMultipleSubOrders(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $subOrder1 = $this->createSubOrder(id: 101, total: 100.00);
        $subOrder2 = $this->createSubOrder(id: 102, total: 150.00);
        $subOrder3 = $this->createSubOrder(id: 103, total: 50.00);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder1, $subOrder2, $subOrder3]);

        $subOrderTransaction1 = $this->createSubOrderTransaction($subOrder1, PaymentMethodInterface::PURCHASE, 201);
        $subOrderTransaction2 = $this->createSubOrderTransaction($subOrder2, PaymentMethodInterface::PURCHASE, 202);
        $subOrderTransaction3 = $this->createSubOrderTransaction($subOrder3, PaymentMethodInterface::PURCHASE, 203);

        $this->subOrderPaymentTransactionFactory
            ->expects(self::exactly(3))
            ->method('createSubOrderPaymentTransaction')
            ->willReturnMap([
                [$parentTransaction, $subOrder1, $subOrderTransaction1],
                [$parentTransaction, $subOrder2, $subOrderTransaction2],
                [$parentTransaction, $subOrder3, $subOrderTransaction3],
            ]);

        $stripePaymentIntent1 = $this->createStripePaymentIntent(status: 'succeeded');
        $stripePaymentIntent2 = new StripePaymentIntent('pi_456');
        $stripePaymentIntent2->status = 'succeeded';
        $stripePaymentIntent2->payment_method = self::STRIPE_PAYMENT_METHOD_ID;
        $stripePaymentIntent2->customer = self::STRIPE_CUSTOMER_ID;

        $stripePaymentIntent3 = new StripePaymentIntent('pi_789');
        $stripePaymentIntent3->status = 'succeeded';
        $stripePaymentIntent3->payment_method = self::STRIPE_PAYMENT_METHOD_ID;
        $stripePaymentIntent3->customer = self::STRIPE_CUSTOMER_ID;

        $this->stripePaymentIntentActionExecutor
            ->expects(self::exactly(3))
            ->method('executeAction')
            ->willReturnCallback(
                function (StripePaymentIntentAction $action) use (
                    $subOrderTransaction1,
                    $subOrderTransaction2,
                    $subOrderTransaction3,
                    $stripePaymentIntent1,
                    $stripePaymentIntent2,
                    $stripePaymentIntent3
                ) {
                    static $callCount = 0;
                    $callCount++;

                    if ($callCount === 1) {
                        // First call - initial transaction
                        self::assertTrue(
                            $subOrderTransaction1->getTransactionOption(
                                StripePaymentIntentActionInterface::SETUP_FUTURE_USAGE
                            )
                        );
                        $subOrderTransaction1->setSuccessful(true);
                        $subOrderTransaction1->setActive(false);
                        $subOrderTransaction1->addTransactionOption(
                            StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                            self::STRIPE_PAYMENT_METHOD_ID
                        );
                        $subOrderTransaction1->addTransactionOption(
                            StripePaymentIntentActionInterface::CUSTOMER_ID,
                            self::STRIPE_CUSTOMER_ID
                        );

                        return new StripePaymentIntentActionResult(true, $stripePaymentIntent1);
                    } elseif ($callCount === 2) {
                        // Second call - subsequent transaction
                        self::assertTrue(
                            $subOrderTransaction2->getTransactionOption(
                                StripePaymentIntentActionInterface::OFF_SESSION
                            )
                        );
                        self::assertEquals(
                            self::STRIPE_PAYMENT_METHOD_ID,
                            $subOrderTransaction2->getTransactionOption(
                                StripePaymentIntentActionInterface::PAYMENT_METHOD_ID
                            )
                        );
                        $subOrderTransaction2->setSuccessful(true);
                        $subOrderTransaction2->setActive(false);

                        return new StripePaymentIntentActionResult(true, $stripePaymentIntent2);
                    } else {
                        // Third call - subsequent transaction
                        self::assertTrue(
                            $subOrderTransaction2->getTransactionOption(
                                StripePaymentIntentActionInterface::OFF_SESSION
                            )
                        );
                        self::assertEquals(
                            self::STRIPE_PAYMENT_METHOD_ID,
                            $subOrderTransaction2->getTransactionOption(
                                StripePaymentIntentActionInterface::PAYMENT_METHOD_ID
                            )
                        );
                        $subOrderTransaction3->setSuccessful(true);
                        $subOrderTransaction3->setActive(false);

                        return new StripePaymentIntentActionResult(true, $stripePaymentIntent3);
                    }
                }
            );

        $this->paymentTransactionProvider
            ->expects(self::exactly(3))
            ->method('savePaymentTransaction')
            ->withConsecutive([$subOrderTransaction1], [$subOrderTransaction2], [$subOrderTransaction3]);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        self::assertSame($stripePaymentIntent3, $result->getStripeObject());
        self::assertTrue($parentTransaction->isSuccessful());
    }

    public function testExecuteActionStopsProcessingWhenSubsequentTransactionFails(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $subOrder1 = $this->createSubOrder(id: 101, total: 100.00);
        $subOrder2 = $this->createSubOrder(id: 102, total: 150.00);
        $subOrder3 = $this->createSubOrder(id: 103, total: 50.00);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder1, $subOrder2, $subOrder3]);

        $subOrderTransaction1 = $this->createSubOrderTransaction($subOrder1, PaymentMethodInterface::PURCHASE, 201);
        $subOrderTransaction2 = $this->createSubOrderTransaction($subOrder2, PaymentMethodInterface::PURCHASE, 202);

        $this->subOrderPaymentTransactionFactory
            ->expects(self::exactly(2))
            ->method('createSubOrderPaymentTransaction')
            ->willReturnMap([
                [$parentTransaction, $subOrder1, $subOrderTransaction1],
                [$parentTransaction, $subOrder2, $subOrderTransaction2],
            ]);

        $stripePaymentIntent1 = $this->createStripePaymentIntent(status: 'succeeded');
        $stripePaymentIntent2 = new StripePaymentIntent('pi_456');
        $stripePaymentIntent2->status = 'requires_payment_method';
        $stripeError = CardException::factory(
            'Your card was declined.',
            null,
            null,
            null,
            null,
            'card_declined'
        );

        $this->stripePaymentIntentActionExecutor
            ->expects(self::exactly(2))
            ->method('executeAction')
            ->willReturnOnConsecutiveCalls(
                (function () use ($subOrderTransaction1, $stripePaymentIntent1) {
                    $subOrderTransaction1->setSuccessful(true);
                    $subOrderTransaction1->setActive(false);
                    $subOrderTransaction1->addTransactionOption(
                        StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                        self::STRIPE_PAYMENT_METHOD_ID
                    );
                    $subOrderTransaction1->addTransactionOption(
                        StripePaymentIntentActionInterface::CUSTOMER_ID,
                        self::STRIPE_CUSTOMER_ID
                    );

                    return new StripePaymentIntentActionResult(true, $stripePaymentIntent1);
                })(),
                (function () use ($subOrderTransaction2, $stripePaymentIntent2, $stripeError) {
                    $subOrderTransaction2->setSuccessful(false);
                    $subOrderTransaction2->setActive(false);

                    return new StripePaymentIntentActionResult(false, $stripePaymentIntent2, $stripeError);
                })()
            );

        $this->paymentTransactionProvider
            ->expects(self::exactly(2))
            ->method('savePaymentTransaction')
            ->withConsecutive([$subOrderTransaction1], [$subOrderTransaction2]);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertFalse($result->isSuccessful());
        self::assertSame($stripePaymentIntent2, $result->getStripeObject());
        self::assertSame($stripeError, $result->getStripeError());
        self::assertFalse($parentTransaction->isSuccessful());
    }

    public function testExecuteActionWhenInitialTransactionRequiresAction(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $subOrder1 = $this->createSubOrder(id: 101, total: 100.00);
        $subOrder2 = $this->createSubOrder(id: 102, total: 150.00);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder1, $subOrder2]);

        $subOrderTransaction1 = $this->createSubOrderTransaction($subOrder1, PaymentMethodInterface::PURCHASE, 201);

        $this->subOrderPaymentTransactionFactory
            ->expects(self::once())
            ->method('createSubOrderPaymentTransaction')
            ->with($parentTransaction, $subOrder1)
            ->willReturn($subOrderTransaction1);

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_action');

        $this->stripePaymentIntentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturnCallback(function () use ($subOrderTransaction1, $stripePaymentIntent) {
                $subOrderTransaction1->setSuccessful(false);
                $subOrderTransaction1->setActive(true);
                $subOrderTransaction1->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    self::STRIPE_PAYMENT_METHOD_ID
                );

                return new StripePaymentIntentActionResult(false, $stripePaymentIntent);
            });

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($subOrderTransaction1);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertFalse($result->isSuccessful());
        self::assertSame($stripePaymentIntent, $result->getStripeObject());
        self::assertNull($result->getStripeError());
        self::assertFalse($parentTransaction->isSuccessful());
        self::assertTrue($parentTransaction->isActive());
    }

    public function testExecuteActionWhenInitialTransactionFailsWithoutRequiringAction(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $subOrder1 = $this->createSubOrder(id: 101, total: 100.00);
        $subOrder2 = $this->createSubOrder(id: 102, total: 150.00);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder1, $subOrder2]);

        $subOrderTransaction1 = $this->createSubOrderTransaction($subOrder1, PaymentMethodInterface::PURCHASE, 201);

        $this->subOrderPaymentTransactionFactory
            ->expects(self::once())
            ->method('createSubOrderPaymentTransaction')
            ->with($parentTransaction, $subOrder1)
            ->willReturn($subOrderTransaction1);

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_payment_method');
        $stripeError = CardException::factory(
            'Your card was declined.',
            null,
            null,
            null,
            null,
            'card_declined'
        );

        $this->stripePaymentIntentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturnCallback(function () use ($subOrderTransaction1, $stripePaymentIntent, $stripeError) {
                $subOrderTransaction1->setSuccessful(false);
                $subOrderTransaction1->setActive(false);

                return new StripePaymentIntentActionResult(false, $stripePaymentIntent, $stripeError);
            });

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($subOrderTransaction1);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Payment failed for the initial payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $subOrderTransaction1->getId(),
                ]
            );

        $result = $this->executor->executeAction($stripeAction);

        self::assertFalse($result->isSuccessful());
        self::assertSame($stripePaymentIntent, $result->getStripeObject());
        self::assertSame($stripeError, $result->getStripeError());
        self::assertFalse($parentTransaction->isSuccessful());
        self::assertFalse($parentTransaction->isActive());
    }

    public function testExecuteActionForChargeAction(): void
    {
        $parentTransaction = $this->createParentTransaction(PaymentMethodInterface::CHARGE);
        $subOrder = $this->createSubOrder(id: 101, total: 100.00);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::CHARGE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder]);

        $subOrderTransaction = $this->createSubOrderTransaction($subOrder, PaymentMethodInterface::CHARGE, 201);

        $this->subOrderPaymentTransactionFactory
            ->expects(self::once())
            ->method('createSubOrderPaymentTransaction')
            ->with($parentTransaction, $subOrder)
            ->willReturn($subOrderTransaction);

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'succeeded');

        $this->stripePaymentIntentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturnCallback(function () use ($subOrderTransaction, $stripePaymentIntent) {
                $subOrderTransaction->setSuccessful(true);
                $subOrderTransaction->setActive(false);
                $subOrderTransaction->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    self::STRIPE_PAYMENT_METHOD_ID
                );
                $subOrderTransaction->addTransactionOption(
                    StripePaymentIntentActionInterface::CUSTOMER_ID,
                    self::STRIPE_CUSTOMER_ID
                );

                return new StripePaymentIntentActionResult(true, $stripePaymentIntent);
            });

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($subOrderTransaction);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        // Parent transaction action is always set to PURCHASE as it's a meta transaction
        self::assertEquals(PaymentMethodInterface::PURCHASE, $parentTransaction->getAction());
    }

    public function testExecuteActionForAuthorizeAction(): void
    {
        $parentTransaction = $this->createParentTransaction(PaymentMethodInterface::AUTHORIZE);
        $subOrder = $this->createSubOrder(101, 100.00);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::AUTHORIZE,
            $this->stripePaymentElementConfig,
            $parentTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder]);

        $subOrderTransaction = $this->createSubOrderTransaction($subOrder, PaymentMethodInterface::AUTHORIZE, 201);

        $this->subOrderPaymentTransactionFactory
            ->expects(self::once())
            ->method('createSubOrderPaymentTransaction')
            ->with($parentTransaction, $subOrder)
            ->willReturn($subOrderTransaction);

        $stripePaymentIntent = $this->createStripePaymentIntent(status: 'requires_capture');

        $this->stripePaymentIntentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturnCallback(function () use ($subOrderTransaction, $stripePaymentIntent) {
                $subOrderTransaction->setSuccessful(true);
                $subOrderTransaction->setActive(true);
                $subOrderTransaction->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    self::STRIPE_PAYMENT_METHOD_ID
                );
                $subOrderTransaction->addTransactionOption(
                    StripePaymentIntentActionInterface::CUSTOMER_ID,
                    self::STRIPE_CUSTOMER_ID
                );

                return new StripePaymentIntentActionResult(true, $stripePaymentIntent);
            });

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($subOrderTransaction);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        // Parent transaction action is always set to PURCHASE as it's a meta transaction
        self::assertEquals(PaymentMethodInterface::PURCHASE, $parentTransaction->getAction());
        self::assertTrue($parentTransaction->isActive());
    }
}
