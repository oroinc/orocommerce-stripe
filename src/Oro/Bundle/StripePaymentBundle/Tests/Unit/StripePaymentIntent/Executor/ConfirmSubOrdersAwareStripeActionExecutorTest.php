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
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\ConfirmStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\ConfirmSubOrdersAwareStripeActionExecutor;
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
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
final class ConfirmSubOrdersAwareStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const string STRIPE_CUSTOMER_ID = 'cus_123';
    private const string STRIPE_PAYMENT_METHOD_ID = 'pm_123';
    private const string SAMPLE_ACCESS_IDENTIFIER = 'sample_identifier';
    private const string SAMPLE_ACCESS_TOKEN = 'sample_token';

    private ConfirmSubOrdersAwareStripeActionExecutor $executor;

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

        $this->executor = new ConfirmSubOrdersAwareStripeActionExecutor(
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

    private function createInitialTransaction(
        PaymentTransaction $parentTransaction,
        int $id = 201
    ): PaymentTransaction {
        $transaction = new PaymentTransaction();
        ReflectionUtil::setId($transaction, $id);
        $transaction->setAction(ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME);
        $transaction->setAccessIdentifier(self::SAMPLE_ACCESS_IDENTIFIER . '_initial');
        $transaction->setAccessToken(self::SAMPLE_ACCESS_TOKEN . '_initial');
        $transaction->setAmount(100.00);
        $transaction->setCurrency('USD');
        $transaction->setPaymentMethod('stripe_payment_element');
        $transaction->setEntityClass(Order::class);
        $transaction->setEntityIdentifier(101);
        $transaction->setSourcePaymentTransaction($parentTransaction);

        return $transaction;
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

    public function testIsSupportedByActionNameReturnsTrueWhenConfirmAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName('confirm'));
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotConfirmAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::PURCHASE));
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::CHARGE));
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::AUTHORIZE));
        self::assertFalse($this->executor->isSupportedByActionName(PaymentMethodInterface::CAPTURE));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotConfirmAction(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $initialTransaction = $this->createInitialTransaction($parentTransaction);
        $initialTransaction->setAction(PaymentMethodInterface::PURCHASE);

        $stripeAction = new StripePaymentIntentAction(
            PaymentMethodInterface::PURCHASE,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoParentTransaction(): void
    {
        $initialTransaction = new PaymentTransaction();
        ReflectionUtil::setId($initialTransaction, 201);
        $initialTransaction->setAction(ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsFalseWhenNoSubOrders(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $initialTransaction = $this->createInitialTransaction($parentTransaction);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
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
        $initialTransaction = $this->createInitialTransaction($parentTransaction);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('hasSubOrders')
            ->with($parentTransaction)
            ->willReturn(true);

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionFailsWhenNoParentTransaction(): void
    {
        $initialTransaction = new PaymentTransaction();
        ReflectionUtil::setId($initialTransaction, 201);
        $initialTransaction->setAction(ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Cannot confirm the payment transaction #{paymentTransactionId}: no parent payment transaction found',
                [
                    'paymentTransactionId' => 201,
                ]
            );

        $this->stripePaymentIntentActionExecutor
            ->expects(self::never())
            ->method('executeAction');

        $result = $this->executor->executeAction($stripeAction);

        self::assertFalse($result->isSuccessful());
        self::assertNull($result->getStripeObject());
    }

    public function testExecuteActionSuccessfullyConfirmsSingleSubOrder(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $initialTransaction = $this->createInitialTransaction($parentTransaction);
        $subOrder = $this->createSubOrder(101, 100.00);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder]);

        $stripePaymentIntent = $this->createStripePaymentIntent('succeeded');

        $this->stripePaymentIntentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturnCallback(
                function (StripePaymentIntentAction $action) use ($initialTransaction, $stripePaymentIntent) {
                    self::assertSame($initialTransaction, $action->getPaymentTransaction());
                    self::assertEquals(ConfirmStripeActionExecutor::ACTION_NAME_EXPLICIT, $action->getActionName());

                    // Simulate successful confirmation
                    $initialTransaction->setSuccessful(true);
                    $initialTransaction->setActive(false);
                    $initialTransaction->addTransactionOption(
                        StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                        self::STRIPE_PAYMENT_METHOD_ID
                    );
                    $initialTransaction->addTransactionOption(
                        StripePaymentIntentActionInterface::CUSTOMER_ID,
                        self::STRIPE_CUSTOMER_ID
                    );

                    return new StripePaymentIntentActionResult(true, $stripePaymentIntent);
                }
            );

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($parentTransaction);

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
    public function testExecuteActionSuccessfullyConfirmsMultipleSubOrders(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $initialTransaction = $this->createInitialTransaction($parentTransaction);

        $subOrder1 = $this->createSubOrder(id: 101, total: 100.00);
        $subOrder2 = $this->createSubOrder(id: 102, total: 150.00);
        $subOrder3 = $this->createSubOrder(id: 103, total: 50.00);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder1, $subOrder2, $subOrder3]);

        $subOrderTransaction2 = $this->createSubOrderTransaction($subOrder2, PaymentMethodInterface::PURCHASE, 302);
        $subOrderTransaction3 = $this->createSubOrderTransaction($subOrder3, PaymentMethodInterface::PURCHASE, 303);

        $this->subOrderPaymentTransactionFactory
            ->expects(self::exactly(2))
            ->method('createSubOrderPaymentTransaction')
            ->willReturnMap([
                [$parentTransaction, $subOrder2, $subOrderTransaction2],
                [$parentTransaction, $subOrder3, $subOrderTransaction3],
            ]);

        $stripePaymentIntent1 = $this->createStripePaymentIntent('succeeded', 'pi_123');
        $stripePaymentIntent2 = $this->createStripePaymentIntent('succeeded', 'pi_456');
        $stripePaymentIntent3 = $this->createStripePaymentIntent('succeeded', 'pi_789');

        $this->stripePaymentIntentActionExecutor
            ->expects(self::exactly(3))
            ->method('executeAction')
            ->willReturnCallback(
                function (StripePaymentIntentAction $action) use (
                    $initialTransaction,
                    $stripePaymentIntent1,
                    $subOrderTransaction2,
                    $subOrderTransaction3,
                    $stripePaymentIntent2,
                    $stripePaymentIntent3,
                    $parentTransaction
                ) {
                    static $callCount = 0;
                    $callCount++;

                    if ($callCount === 1) {
                        $initialTransaction->setSuccessful(true);
                        $initialTransaction->setActive(false);
                        $initialTransaction->addTransactionOption(
                            StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                            self::STRIPE_PAYMENT_METHOD_ID
                        );
                        $initialTransaction->addTransactionOption(
                            StripePaymentIntentActionInterface::CUSTOMER_ID,
                            self::STRIPE_CUSTOMER_ID
                        );

                        return new StripePaymentIntentActionResult(true, $stripePaymentIntent1);
                    } elseif ($callCount === 2) {
                        self::assertSame($subOrderTransaction2, $action->getPaymentTransaction());
                        self::assertEquals($initialTransaction->getAction(), $action->getActionName());
                        self::assertTrue(
                            $action->getPaymentTransaction()->getTransactionOption(
                                StripePaymentIntentActionInterface::OFF_SESSION
                            )
                        );
                        $subOrderTransaction2->setSuccessful(true);
                        $subOrderTransaction2->setActive(false);

                        return new StripePaymentIntentActionResult(true, $stripePaymentIntent2);
                    } else {
                        self::assertSame($subOrderTransaction3, $action->getPaymentTransaction());
                        self::assertEquals($initialTransaction->getAction(), $action->getActionName());
                        self::assertTrue(
                            $action->getPaymentTransaction()->getTransactionOption(
                                StripePaymentIntentActionInterface::OFF_SESSION
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
            ->withConsecutive([$subOrderTransaction2], [$subOrderTransaction3], [$parentTransaction]);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        self::assertSame($stripePaymentIntent3, $result->getStripeObject());
        self::assertTrue($parentTransaction->isSuccessful());
    }

    public function testExecuteActionStopsProcessingWhenSubsequentTransactionFails(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $initialTransaction = $this->createInitialTransaction($parentTransaction);

        $subOrder1 = $this->createSubOrder(id: 101, total: 100.00);
        $subOrder2 = $this->createSubOrder(id: 102, total: 150.00);
        $subOrder3 = $this->createSubOrder(id: 103, total: 50.00);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder1, $subOrder2, $subOrder3]);

        $subOrderTransaction2 = $this->createSubOrderTransaction($subOrder2, PaymentMethodInterface::PURCHASE, 302);

        $this->subOrderPaymentTransactionFactory
            ->expects(self::once())
            ->method('createSubOrderPaymentTransaction')
            ->with($parentTransaction, $subOrder2)
            ->willReturn($subOrderTransaction2);

        $stripePaymentIntent1 = $this->createStripePaymentIntent('succeeded', 'pi_123');
        $stripePaymentIntent2 = $this->createStripePaymentIntent('requires_payment_method', 'pi_456');
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
                (function () use ($initialTransaction, $stripePaymentIntent1) {
                    $initialTransaction->setSuccessful(true);
                    $initialTransaction->setActive(false);
                    $initialTransaction->addTransactionOption(
                        StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                        self::STRIPE_PAYMENT_METHOD_ID
                    );
                    $initialTransaction->addTransactionOption(
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
            ->withConsecutive([$subOrderTransaction2], [$parentTransaction]);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertFalse($result->isSuccessful());
        self::assertSame($stripePaymentIntent2, $result->getStripeObject());
        self::assertSame($stripeError, $result->getStripeError());
        self::assertFalse($parentTransaction->isSuccessful());
    }

    public function testExecuteActionWhenInitialConfirmationFails(): void
    {
        $parentTransaction = $this->createParentTransaction();
        $initialTransaction = $this->createInitialTransaction($parentTransaction);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::never())
            ->method('getSubOrders');

        $stripePaymentIntent = $this->createStripePaymentIntent('requires_payment_method');
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
            ->willReturnCallback(function () use ($initialTransaction, $stripePaymentIntent, $stripeError) {
                $initialTransaction->setSuccessful(false);
                $initialTransaction->setActive(false);

                return new StripePaymentIntentActionResult(false, $stripePaymentIntent, $stripeError);
            });

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Cannot confirm the initial payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $initialTransaction->getId(),
                ]
            );

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($parentTransaction);

        $result = $this->executor->executeAction($stripeAction);

        self::assertFalse($result->isSuccessful());
        self::assertSame($stripePaymentIntent, $result->getStripeObject());
        self::assertSame($stripeError, $result->getStripeError());
        self::assertFalse($parentTransaction->isSuccessful());
    }

    public function testExecuteActionForChargeAction(): void
    {
        $parentTransaction = $this->createParentTransaction(PaymentMethodInterface::CHARGE);
        $initialTransaction = $this->createInitialTransaction($parentTransaction);
        $subOrder = $this->createSubOrder(101, 100.00);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder]);

        $stripePaymentIntent = $this->createStripePaymentIntent('succeeded');

        $this->stripePaymentIntentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturnCallback(function () use ($initialTransaction, $stripePaymentIntent) {
                $initialTransaction->setSuccessful(true);
                $initialTransaction->setActive(false);
                $initialTransaction->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    self::STRIPE_PAYMENT_METHOD_ID
                );
                $initialTransaction->addTransactionOption(
                    StripePaymentIntentActionInterface::CUSTOMER_ID,
                    self::STRIPE_CUSTOMER_ID
                );

                return new StripePaymentIntentActionResult(true, $stripePaymentIntent);
            });

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($parentTransaction);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        // Parent transaction action is always set to PURCHASE as it's a meta transaction
        self::assertEquals(PaymentMethodInterface::PURCHASE, $parentTransaction->getAction());
        self::assertTrue($parentTransaction->isSuccessful());
        self::assertFalse($parentTransaction->isActive());
    }

    public function testExecuteActionForAuthorizeAction(): void
    {
        $parentTransaction = $this->createParentTransaction(PaymentMethodInterface::AUTHORIZE);
        $initialTransaction = $this->createInitialTransaction($parentTransaction);
        $subOrder = $this->createSubOrder(id: 101, total: 100.00);

        $stripeAction = new StripePaymentIntentAction(
            ConfirmSubOrdersAwareStripeActionExecutor::ACTION_NAME,
            $this->stripePaymentElementConfig,
            $initialTransaction
        );

        $this->subOrdersByPaymentTransactionProvider
            ->expects(self::once())
            ->method('getSubOrders')
            ->with($parentTransaction)
            ->willReturn([$subOrder]);

        $stripePaymentIntent = $this->createStripePaymentIntent('requires_capture');

        $this->stripePaymentIntentActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->willReturnCallback(function () use ($initialTransaction, $stripePaymentIntent) {
                $initialTransaction->setSuccessful(true);
                $initialTransaction->setActive(true);
                $initialTransaction->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    self::STRIPE_PAYMENT_METHOD_ID
                );
                $initialTransaction->addTransactionOption(
                    StripePaymentIntentActionInterface::CUSTOMER_ID,
                    self::STRIPE_CUSTOMER_ID
                );

                return new StripePaymentIntentActionResult(true, $stripePaymentIntent);
            });

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('savePaymentTransaction')
            ->with($parentTransaction);

        $this->assertLoggerNotCalled();

        $result = $this->executor->executeAction($stripeAction);

        self::assertTrue($result->isSuccessful());
        // Parent transaction action is always set to PURCHASE as it's a meta transaction
        self::assertEquals(PaymentMethodInterface::PURCHASE, $parentTransaction->getAction());
        self::assertTrue($parentTransaction->isActive());
        self::assertTrue($parentTransaction->isSuccessful());
    }
}
