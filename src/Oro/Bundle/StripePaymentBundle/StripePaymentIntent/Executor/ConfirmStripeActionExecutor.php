<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Brick\Math\BigDecimal;
use Oro\Bundle\CurrencyBundle\DependencyInjection\Configuration as CurrencyConfiguration;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Performs "confirm" payment method action.
 *
 * @see https://docs.stripe.com/api/payment_intents
 */
class ConfirmStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const string ACTION_NAME = 'confirm';

    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly StripeAmountConverterInterface $stripeAmountConverter,
        private readonly EventDispatcherInterface $eventDispatcher
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
        if (!$purchaseTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID)) {
            $this->logNoStripePaymentIntentId($purchaseTransaction);

            return false;
        }

        return true;
    }

    private function logNoStripePaymentIntentId(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->notice(
            'Cannot confirm the payment transaction #{paymentTransactionId}: stripePaymentIntentId is not found',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        $purchaseTransaction = $stripeAction->getPaymentTransaction();

        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());
        $stripeClient->beginScopeFor($purchaseTransaction);

        $requestArgs = $this->prepareRequestArgs($purchaseTransaction, $stripeAction);
        $paymentIntent = $stripeClient->paymentIntents->retrieve(...$requestArgs);
        $this->processRequestResult($paymentIntent, $purchaseTransaction);

        return new StripePaymentIntentActionResult(
            successful: $purchaseTransaction->isSuccessful(),
            stripePaymentIntent: $paymentIntent
        );
    }

    private function prepareRequestArgs(
        PaymentTransaction $purchaseTransaction,
        StripePaymentIntentActionInterface $stripeAction
    ): array {
        $requestArgs = [
            $purchaseTransaction->getTransactionOption(
                StripePaymentIntentActionInterface::PAYMENT_INTENT_ID
            ),
        ];
        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsRetrieve',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }

    private function processRequestResult(
        StripePaymentIntent $paymentIntent,
        PaymentTransaction $purchaseTransaction
    ): void {
        // More about Payment Intent statuses:
        // https://docs.stripe.com/payments/paymentintents/lifecycle#intent-statuses
        $successful = in_array($paymentIntent->status, ['succeeded', 'requires_capture', 'processing']);
        $requiresCapture = $paymentIntent->status === 'requires_capture';

        $paymentIntentCurrency = mb_strtoupper($paymentIntent->currency ?? CurrencyConfiguration::DEFAULT_CURRENCY);
        $paymentIntentAmount = $this->stripeAmountConverter->convertFromStripeFormat(
            $paymentIntent->amount ?? 0,
            $paymentIntentCurrency
        );

        $purchaseTransaction->setSuccessful(
            $successful &&
            BigDecimal::of($purchaseTransaction->getAmount())->isEqualTo(BigDecimal::of($paymentIntentAmount)) &&
            $purchaseTransaction->getCurrency() === $paymentIntentCurrency
        );

        $purchaseTransaction->setActive($requiresCapture);
    }
}
