<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Brick\Math\BigDecimal;
use Oro\Bundle\CurrencyBundle\DependencyInjection\Configuration as CurrencyConfiguration;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
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
 * Performs "capture" payment method action.
 *
 * @see https://docs.stripe.com/api/payment_intents
 */
class CaptureStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

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
        return $stripeActionName === PaymentMethodInterface::CAPTURE;
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$this->isSupportedByActionName($stripeAction->getActionName())) {
            return false;
        }

        $captureTransaction = $stripeAction->getPaymentTransaction();
        $sourceTransaction = $captureTransaction->getSourcePaymentTransaction();
        if (!$sourceTransaction) {
            $this->logNoSourcePaymentTransaction($captureTransaction);

            return false;
        }

        if ($sourceTransaction->getAction() !== PaymentMethodInterface::AUTHORIZE) {
            return false;
        }

        if (!$sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID)) {
            $this->logNoStripePaymentIntentId($captureTransaction, $sourceTransaction);

            return false;
        }

        return true;
    }

    private function logNoSourcePaymentTransaction(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->notice(
            'Cannot capture the payment transaction #{paymentTransactionId}: no source payment transaction',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }

    private function logNoStripePaymentIntentId(
        PaymentTransaction $paymentTransaction,
        PaymentTransaction $sourcePaymentTransaction
    ): void {
        $this->logger->notice(
            'Cannot capture the payment transaction #{paymentTransactionId}: '
            . 'stripePaymentIntentId is not found in #{sourcePaymentTransactionId}',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
                'sourcePaymentTransactionId' => $sourcePaymentTransaction->getId(),
            ]
        );
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        $captureTransaction = $stripeAction->getPaymentTransaction();

        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());
        $stripeClient->beginScopeFor($captureTransaction);

        $requestArgs = $this->prepareRequestArgs($stripeAction, $captureTransaction);
        $paymentIntent = $stripeClient->paymentIntents->capture(...$requestArgs);
        $this->processRequestResult($paymentIntent, $captureTransaction);

        return new StripePaymentIntentActionResult(
            successful: $captureTransaction->isSuccessful(),
            stripePaymentIntent: $paymentIntent
        );
    }

    private function prepareRequestArgs(
        StripePaymentIntentActionInterface $stripeAction,
        PaymentTransaction $captureTransaction
    ): array {
        /** @var PaymentTransaction $sourceTransaction */
        $sourceTransaction = $captureTransaction->getSourcePaymentTransaction();

        $requestArgs = [
            $sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID),
            [
                'amount_to_capture' => $this->stripeAmountConverter->convertToStripeFormat(
                    (float)$captureTransaction->getAmount(),
                    $captureTransaction->getCurrency()
                ),
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCapture',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }

    private function processRequestResult(
        StripePaymentIntent $paymentIntent,
        PaymentTransaction $captureTransaction
    ): void {
        // More about Payment Intent statuses:
        // https://docs.stripe.com/payments/paymentintents/lifecycle#intent-statuses
        $successful = in_array($paymentIntent->status, ['succeeded', 'processing']);

        $currencyCaptured = mb_strtoupper($paymentIntent->currency ?? CurrencyConfiguration::DEFAULT_CURRENCY);
        $amountCaptured = $this->stripeAmountConverter->convertFromStripeFormat(
            $paymentIntent->amount_received ?? 0,
            $currencyCaptured
        );

        $captureTransaction->setAction(PaymentMethodInterface::CAPTURE);
        $captureTransaction->setSuccessful(
            $successful &&
            BigDecimal::of($captureTransaction->getAmount())->isEqualTo(BigDecimal::of($amountCaptured)) &&
            $captureTransaction->getCurrency() === $currencyCaptured
        );
        $captureTransaction->setActive(false);
        $captureTransaction->setReference($paymentIntent->id);
        $captureTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
            $paymentIntent->id
        );

        /** @var PaymentTransaction $sourceTransaction */
        $sourceTransaction = $captureTransaction->getSourcePaymentTransaction();
        // The source transaction has to be switched to inactive state after the capture.
        $sourceTransaction->setActive(!$captureTransaction->isSuccessful());
    }
}
