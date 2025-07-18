<?php

namespace Oro\Bundle\StripePaymentBundle\EventListener\PaymentCallback;

use Oro\Bundle\PaymentBundle\Event\AbstractCallbackEvent;
use Oro\Bundle\PaymentBundle\Event\PaymentCallbackListenerInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\ConfirmStripeActionExecutor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles the payment result for payment method that redirects a user back to return URL during payment process.
 *
 * @see https://docs.stripe.com/payments/payment-methods/payment-method-support#additional-api-supportability for
 * payment methods that require a redirect.
 */
final class StripePaymentIntentsReturnCallbackListener implements
    PaymentCallbackListenerInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private PaymentMethodProviderInterface $paymentMethodProvider
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function onPaymentCallback(AbstractCallbackEvent $event): void
    {
        $paymentTransaction = $event->getPaymentTransaction();
        if (!$paymentTransaction) {
            return;
        }

        $paymentMethodIdentifier = $paymentTransaction->getPaymentMethod();
        if (false === $this->paymentMethodProvider->hasPaymentMethod($paymentMethodIdentifier)) {
            return;
        }

        $paymentTransaction->addTransactionOption(
            'returnData',
            array_merge($paymentTransaction->getTransactionOption('returnData') ?? [], $event->getData())
        );

        try {
            $paymentMethod = $this->paymentMethodProvider->getPaymentMethod($paymentMethodIdentifier);
            $response = $paymentMethod->execute(
                ConfirmStripeActionExecutor::ACTION_NAME,
                $paymentTransaction
            );
            $successful = $response['successful'] ?? false;
            $isPartiallyPaid = $response['isPartiallyPaid'] ?? false;
        } catch (\Throwable $e) {
            $successful = false;
            $isPartiallyPaid = false;

            $this->logger->error('Failed to handle the return URL for transaction #{transactionId}: {message}', [
                'message' => $e->getMessage(),
                'throwable' => $e,
                'transactionId' => $paymentTransaction->getId(),
            ]);
        }

        if ($successful === true) {
            $event->markSuccessful();
        } else {
            $event->markFailed();

            $failureUrl = $paymentTransaction->getTransactionOption('failureUrl');
            $partiallyPaidUrl = $paymentTransaction->getTransactionOption('partiallyPaidUrl');
            if ($isPartiallyPaid) {
                $redirectUrl = $partiallyPaidUrl ?? $failureUrl;
            } else {
                $redirectUrl = $failureUrl;
            }

            if ($redirectUrl) {
                $event->setResponse(new RedirectResponse($redirectUrl));
            }
        }
    }
}
