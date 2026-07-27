<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor\StripeCustomerActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Performs "charge" payment method action with the "off_session" option.
 * The difference from the regular "charge" action is that this action is intended to be used
 * for charging via a previously authorized payment method - without user interaction.
 *
 * @see https://docs.stripe.com/api/payment_intents
 * @see https://docs.stripe.com/api/payment_methods
 */
class ChargeOffSessionStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly StripeCustomerActionExecutorInterface $stripeCustomerActionExecutor,
        private readonly StripeAmountConverterInterface $stripeAmountConverter,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return in_array(
            $stripeActionName,
            [PaymentMethodInterface::PURCHASE, PaymentMethodInterface::CHARGE],
            true
        );
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$this->isSupportedByActionName($stripeAction->getActionName())) {
            return false;
        }

        $chargeTransaction = $stripeAction->getPaymentTransaction();
        if (!$chargeTransaction->getTransactionOption(StripePaymentIntentActionInterface::OFF_SESSION)) {
            return false;
        }

        $paymentMethodId = $chargeTransaction
            ->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_METHOD_ID);
        if (!$paymentMethodId) {
            $this->logger->info(
                'Skipping "charge" action: no payment method id found in the options '
                . 'of the payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $chargeTransaction->getId(),
                ]
            );

            return false;
        }

        $stripePaymentIntentConfig = $stripeAction->getPaymentIntentConfig();

        return $stripeAction->getActionName() === PaymentMethodInterface::CHARGE ||
            $stripePaymentIntentConfig->getCaptureMethod() === 'automatic';
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        $chargeTransaction = $stripeAction->getPaymentTransaction();

        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());
        $stripeClient->beginScopeFor($chargeTransaction);

        $requestArgs = $this->prepareRequestArgs($stripeAction, $chargeTransaction);
        $paymentIntent = $stripeClient->paymentIntents->create(...$requestArgs);
        $this->processRequestResult($paymentIntent, $chargeTransaction);

        return new StripePaymentIntentActionResult(
            successful: $chargeTransaction->isSuccessful(),
            stripePaymentIntent: $paymentIntent
        );
    }

    private function prepareRequestArgs(
        StripePaymentIntentActionInterface $stripeAction,
        PaymentTransaction $chargeTransaction
    ): array {
        $stripeAmount = $this->stripeAmountConverter->convertToStripeFormat(
            (float)$chargeTransaction->getAmount(),
            $chargeTransaction->getCurrency()
        );
        $stripePaymentMethodId = $chargeTransaction
            ->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_METHOD_ID);
        $requestArgs = [
            [
                'amount' => $stripeAmount,
                'currency' => $chargeTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => $stripePaymentMethodId,
                'metadata' => [
                    'payment_transaction_access_identifier' => $chargeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $chargeTransaction->getAccessToken(),
                ],
            ],
        ];

        $stripeClientConfig = $stripeAction->getStripeClientConfig();
        $stripeCustomerId = $this->findOrCreateStripeCustomerId($stripeClientConfig, $chargeTransaction);
        if ($stripeCustomerId) {
            $requestArgs[0]['customer'] = $stripeCustomerId;
        }

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCreate',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }

    private function findOrCreateStripeCustomerId(
        StripeClientConfigInterface $stripeClientConfig,
        PaymentTransaction $chargeTransaction
    ): ?string {
        $stripeCustomerId = $chargeTransaction
            ->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID);

        if (!$stripeCustomerId) {
            $stripeActionResult = $this->stripeCustomerActionExecutor->executeAction(
                new FindOrCreateStripeCustomerAction(
                    stripeClientConfig: $stripeClientConfig,
                    paymentTransaction: $chargeTransaction
                )
            );

            $stripeCustomerId = $stripeActionResult->getStripeObject()?->id;
            if ($stripeCustomerId === null) {
                $this->logNoStripeCustomerId($chargeTransaction);
            }
        }

        return $stripeCustomerId;
    }

    private function processRequestResult(
        StripePaymentIntent $paymentIntent,
        PaymentTransaction $chargeTransaction
    ): void {
        // More about Payment Intent statuses:
        // https://docs.stripe.com/payments/paymentintents/lifecycle#intent-statuses
        $successful = in_array($paymentIntent->status, ['succeeded', 'processing']);
        $requiresAction = $paymentIntent->status === 'requires_action';

        $chargeTransaction->setAction(PaymentMethodInterface::CHARGE);
        $chargeTransaction->setSuccessful($successful);
        // Active if the transaction is waiting for action.
        $chargeTransaction->setActive($requiresAction);
        $chargeTransaction->setReference($paymentIntent->id);

        $chargeTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
            $paymentIntent->id
        );
        $chargeTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
            $paymentIntent->payment_method ?? ''
        );
        $chargeTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::CUSTOMER_ID,
            $paymentIntent->customer ?? ''
        );
    }

    private function logNoStripeCustomerId(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->notice(
            'Cannot find or create a Stripe customer for the payment transaction #{paymentTransactionId}',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }
}
