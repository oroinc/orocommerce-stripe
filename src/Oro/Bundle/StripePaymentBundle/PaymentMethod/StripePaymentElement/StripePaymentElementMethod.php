<?php

namespace Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodGroupAwareInterface;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeAmountValidator\StripeAmountValidatorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEvent;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEventHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Represents the Stripe Payment Element payment method.
 */
class StripePaymentElementMethod implements
    PaymentMethodInterface,
    StripeWebhookEventHandlerInterface,
    PaymentMethodGroupAwareInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param StripePaymentElementConfig $stripePaymentElementConfig
     * @param StripePaymentIntentActionExecutorInterface $stripePaymentActionExecutor
     * @param StripeAmountValidatorInterface $stripeAmountValidator
     * @param PaymentTransactionProvider $paymentTransactionProvider
     * @param array<string> $paymentMethodGroups Payment method groups the payment method applicable for.
     */
    public function __construct(
        private readonly StripePaymentElementConfig $stripePaymentElementConfig,
        private readonly StripePaymentIntentActionExecutorInterface $stripePaymentActionExecutor,
        private readonly StripeAmountValidatorInterface $stripeAmountValidator,
        private readonly PaymentTransactionProvider $paymentTransactionProvider,
        private readonly array $paymentMethodGroups
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function execute($action, PaymentTransaction $paymentTransaction): array
    {
        try {
            // Saves the payment transaction even before the payment action is executed to win the race condition
            // when the Stripe webhook comes before the payment transaction is persisted to database.
            $this->paymentTransactionProvider->savePaymentTransaction($paymentTransaction);

            $stripePaymentActionResult = $this->stripePaymentActionExecutor->executeAction(
                new StripePaymentIntentAction(
                    actionName: $action,
                    stripePaymentIntentConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $paymentTransaction
                )
            );

            return $stripePaymentActionResult->toArray();
        } catch (\Throwable $throwable) {
            $this->logger->error(
                'Failed to execute a payment action {action} for transaction #{paymentTransactionId}: {message}',
                [
                    'action' => $action,
                    'message' => $throwable->getMessage(),
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'throwable' => $throwable,
                ]
            );

            return [
                'successful' => false,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    #[\Override]
    public function onWebhookEvent(StripeWebhookEvent $event): void
    {
        /** @var PaymentTransaction $paymentTransaction */
        $paymentTransaction = $event->getPaymentTransaction();
        $stripeEvent = $event->getStripeEvent();

        try {
            $stripeActionResult = $this->stripePaymentActionExecutor->executeAction(
                new StripePaymentIntentWebhookAction(
                    stripeEvent: $stripeEvent,
                    stripePaymentIntentConfig: $this->stripePaymentElementConfig,
                    paymentTransaction: $paymentTransaction,
                )
            );

            if ($stripeActionResult->isSuccessful()) {
                $event->markSuccessful();
            } else {
                $event->markFailed();
            }
        } catch (\Throwable $throwable) {
            $event->markFailed();

            $this->logger->error(
                'Failed to process the Stripe Event webhook for payment transaction #{paymentTransactionId}: {message}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'message' => $throwable->getMessage(),
                    'stripeEvent' => $stripeEvent->toArray(),
                    'throwable' => $throwable,
                ]
            );
        }
    }

    #[\Override]
    public function getIdentifier(): string
    {
        return $this->stripePaymentElementConfig->getPaymentMethodIdentifier();
    }

    #[\Override]
    public function isApplicable(PaymentContextInterface $context): bool
    {
        $amount = $context->getTotal();
        $currency = $context->getCurrency();

        return
            $this->stripeAmountValidator->isAboveMinimum($amount, $currency) &&
            $this->stripeAmountValidator->isBelowMaximum($amount, $currency);
    }

    #[\Override]
    public function supports($actionName): bool
    {
        return $this->stripePaymentActionExecutor->isSupportedByActionName($actionName);
    }

    #[\Override]
    public function isApplicableForGroup(string $groupName): bool
    {
        return in_array($groupName, $this->paymentMethodGroups, true);
    }
}
