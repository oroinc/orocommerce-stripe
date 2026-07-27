<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\Factory\SubOrderPaymentTransactionFactory;
use Oro\Bundle\StripePaymentBundle\Provider\SubOrdersByPaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Performs "confirm" payment method action for multiple PaymentIntents created for sub-orders.
 *
 * @see https://docs.stripe.com/api/payment_intents
 */
class ConfirmSubOrdersAwareStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const string ACTION_NAME = 'confirm';

    public function __construct(
        private readonly StripePaymentIntentActionExecutorInterface $stripePaymentIntentActionExecutor,
        private readonly SubOrdersByPaymentTransactionProvider $subOrderPaymentTransactionProvider,
        private readonly SubOrderPaymentTransactionFactory $subOrderPaymentTransactionFactory,
        private readonly PaymentTransactionProvider $paymentTransactionProvider
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return $stripeActionName === self::ACTION_NAME;
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$this->isSupportedByActionName($stripeAction->getActionName())) {
            return false;
        }

        $purchaseTransaction = $stripeAction->getPaymentTransaction();
        $parentTransaction = $purchaseTransaction->getSourcePaymentTransaction();
        if (!$parentTransaction) {
            return false;
        }

        if (!$this->subOrderPaymentTransactionProvider->hasSubOrders($parentTransaction)) {
            return false;
        }

        return true;
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        $initialTransaction = $stripeAction->getPaymentTransaction();
        $parentTransaction = $initialTransaction->getSourcePaymentTransaction();
        if (!$parentTransaction) {
            $this->logNoParentTransaction($initialTransaction);

            return new StripePaymentIntentActionResult(successful: false);
        }

        $initialActionResult = $this->stripePaymentIntentActionExecutor
            ->executeAction(
                new StripePaymentIntentAction(
                    actionName: ConfirmStripeActionExecutor::ACTION_NAME_EXPLICIT,
                    stripePaymentIntentConfig: $stripeAction->getPaymentIntentConfig(),
                    paymentTransaction: $initialTransaction
                )
            );

        if ($initialActionResult->isSuccessful()) {
            $subOrders = $this->subOrderPaymentTransactionProvider->getSubOrders($parentTransaction);
            // Skips the initial sub-order as it is already processed and confirmed.
            array_shift($subOrders);

            $stripePaymentMethodId = $initialTransaction
                ->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_METHOD_ID);
            $stripeCustomerId = $initialTransaction
                ->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID);

            foreach ($subOrders as $subOrder) {
                $subsequentTransaction = $this->subOrderPaymentTransactionFactory
                    ->createSubOrderPaymentTransaction($parentTransaction, $subOrder);
                $subsequentTransaction->addTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
                    $stripePaymentMethodId
                );
                $subsequentTransaction
                    ->addTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID, $stripeCustomerId);
                $subsequentTransaction->addTransactionOption(StripePaymentIntentActionInterface::OFF_SESSION, true);

                // Subsequent transactions should have the same action as the initial transaction.
                $subsequentActionResult = $this->stripePaymentIntentActionExecutor
                    ->executeAction(
                        new StripePaymentIntentAction(
                            actionName: $initialTransaction->getAction(),
                            stripePaymentIntentConfig: $stripeAction->getPaymentIntentConfig(),
                            paymentTransaction: $subsequentTransaction
                        )
                    );
                $this->paymentTransactionProvider->savePaymentTransaction($subsequentTransaction);

                if (!$subsequentActionResult->isSuccessful()) {
                    // Stops processing subsequent sub-orders if one of them fails.
                    break;
                }
            }
        } else {
            // If the initial transaction is not successful - it is an error state which does not assume
            // further processing of subsequent sub-orders.
            $this->logInitialTransactionFailed($initialTransaction);
        }

        // Aligns the parent transaction state with the last processed payment transaction state.
        $this->updateParentTransaction(
            $subsequentTransaction ?? $initialTransaction,
            $parentTransaction
        );

        if (isset($subsequentActionResult)) {
            $lastStripePaymentIntent = $subsequentActionResult->getStripeObject();
            $lastStripeError = $subsequentActionResult->getStripeError();
        } else {
            $lastStripePaymentIntent = $initialActionResult->getStripeObject();
            $lastStripeError = $initialActionResult->getStripeError();
        }

        return new StripePaymentIntentActionResult(
            successful: $parentTransaction->isSuccessful(),
            stripePaymentIntent: $lastStripePaymentIntent,
            stripeError: $lastStripeError
        );
    }

    private function logNoParentTransaction(PaymentTransaction $initialTransaction): void
    {
        $this->logger->error(
            'Cannot confirm the payment transaction #{paymentTransactionId}: '
            . 'no parent payment transaction found',
            [
                'paymentTransactionId' => $initialTransaction->getId(),
            ]
        );
    }

    private function logInitialTransactionFailed(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->error(
            'Cannot confirm the initial payment transaction #{paymentTransactionId}',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }

    private function updateParentTransaction(
        PaymentTransaction $childTransaction,
        PaymentTransaction $parentTransaction
    ): void {
        // Action is set to purchase on purpose - as it is assumed that it cannot have a specific
        // action - it is a meta transaction and no actual actions can be performed on it. All real interactions
        // with the Stripe API are performed on child transactions.
        $parentTransaction->setAction(PaymentMethodInterface::PURCHASE);
        $parentTransaction->setSuccessful($childTransaction->isSuccessful());
        $parentTransaction->setActive($childTransaction->isActive());

        $this->paymentTransactionProvider->savePaymentTransaction($parentTransaction);
    }
}
