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
use Stripe\PaymentIntent as StripePaymentIntent;

/**
 * Executor for creating multiple PaymentIntents for sub-orders using a single confirmation token.
 *
 * @see https://docs.stripe.com/api/payment_intents
 * @see https://docs.stripe.com/api/confirmation_tokens
 */
class PurchaseSubOrdersAwareStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripePaymentIntentActionExecutorInterface $stripePaymentIntentActionExecutor,
        private readonly SubOrdersByPaymentTransactionProvider $subOrdersByPaymentTransactionProvider,
        private readonly SubOrderPaymentTransactionFactory $subOrderPaymentTransactionFactory,
        private readonly PaymentTransactionProvider $paymentTransactionProvider
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return in_array(
            $stripeActionName,
            [PaymentMethodInterface::PURCHASE, PaymentMethodInterface::CHARGE, PaymentMethodInterface::AUTHORIZE],
            true
        );
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$this->isSupportedByActionName($stripeAction->getActionName())) {
            return false;
        }

        $paymentTransaction = $stripeAction->getPaymentTransaction();

        return $this->subOrdersByPaymentTransactionProvider->hasSubOrders($paymentTransaction);
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        $parentTransaction = $stripeAction->getPaymentTransaction();

        $subOrders = $this->subOrdersByPaymentTransactionProvider->getSubOrders($parentTransaction);
        $initialSubOrder = array_shift($subOrders);
        if (!$initialSubOrder) {
            $this->logNoSubOrders($parentTransaction);

            return new StripePaymentIntentActionResult(successful: false);
        }

        $initialTransaction = $this->subOrderPaymentTransactionFactory
            ->createSubOrderPaymentTransaction($parentTransaction, $initialSubOrder);
        $initialTransaction->addTransactionOption(StripePaymentIntentActionInterface::SETUP_FUTURE_USAGE, true);
        $initialActionResult = $this->stripePaymentIntentActionExecutor
            ->executeAction(
                new StripePaymentIntentAction(
                    actionName: $parentTransaction->getAction(),
                    stripePaymentIntentConfig: $stripeAction->getPaymentIntentConfig(),
                    paymentTransaction: $initialTransaction
                )
            );
        $this->paymentTransactionProvider->savePaymentTransaction($initialTransaction);

        /** @var StripePaymentIntent $initialPaymentIntent */
        $initialPaymentIntent = $initialActionResult->getStripeObject();
        $requiresAction = $initialPaymentIntent?->status === 'requires_action';

        if ($initialActionResult->isSuccessful()) {
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
        } elseif (!$requiresAction) {
            // If the initial transaction is not successful and not requires an action - it is an error state
            // which does not assume creation of subsequent transactions.
            $this->logInitialTransactionFailed($initialTransaction);
        } else {
            // If the initial transaction is not successful but requires an action - it is a legitimate state
            // that should be further handled in {@link ConfirmSubOrdersAwareStripeActionExecutor}.
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

    private function logNoSubOrders(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->error(
            'Cannot process the payment transaction #{paymentTransactionId}: no sub-orders found',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }

    private function logInitialTransactionFailed(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->error(
            'Payment failed for the initial payment transaction #{paymentTransactionId}',
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
    }
}
