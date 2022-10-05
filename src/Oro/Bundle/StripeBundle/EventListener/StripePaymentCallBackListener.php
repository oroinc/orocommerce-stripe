<?php

namespace Oro\Bundle\StripeBundle\EventListener;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Event\AbstractCallbackEvent;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentResultMessageProviderInterface;
use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Handle payments after additional user actions (like 3D Secure validation or others).
 */
class StripePaymentCallBackListener
{
    private PaymentMethodProviderInterface $paymentMethodProvider;
    private Session $session;
    private LoggerInterface $logger;
    private PaymentResultMessageProviderInterface $messageProvider;

    public function __construct(
        PaymentMethodProviderInterface $paymentMethodProvider,
        Session $session,
        PaymentResultMessageProviderInterface $messageProvider
    ) {
        $this->paymentMethodProvider = $paymentMethodProvider;
        $this->session = $session;
        $this->messageProvider = $messageProvider;
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function onReturn(AbstractCallbackEvent $event)
    {
        $paymentTransaction = $event->getPaymentTransaction();
        if (!$paymentTransaction) {
            return;
        }

        $paymentMethodId = $paymentTransaction->getPaymentMethod();
        if (false === $this->paymentMethodProvider->hasPaymentMethod($paymentMethodId)) {
            return;
        }

        $eventData = $event->getData();
        $this->updateTransactionOptions($paymentTransaction, $eventData);

        $response = [];
        try {
            $successful = false;
            $paymentMethod = $this->paymentMethodProvider->getPaymentMethod($paymentMethodId);
            $response = $paymentMethod->execute(PaymentActionInterface::CONFIRM_ACTION, $paymentTransaction);
            $successful = $response['successful'];
        } catch (StripeApiException $stripeException) {
            $this->logger->error($stripeException->getMessage(), [
                'error' => $stripeException->getMessage(),
                'stripe_error_code' => $stripeException->getStripeErrorCode(),
                'decline_code' => $stripeException->getDeclineCode(),
                'exception' => $stripeException
            ]);
        } catch (\Throwable $e) {
            $this->logger->critical($e->getMessage(), [
                'error' => $e->getMessage(),
                'exception' => $e
            ]);
        }

        if ($successful === true) {
            $event->markSuccessful();
        } else {
            $event->markFailed();
            $failureUrl = $this->getFailureUrl($paymentTransaction);
            $partiallyPaidUrl = $this->getPartiallyPaidUrl($paymentTransaction) ?? $failureUrl;
            if ($this->isPartiallySuccessfulResponse($response)) {
                if ($partiallyPaidUrl) {
                    $event->setResponse(new RedirectResponse($partiallyPaidUrl));
                }
            } elseif ($failureUrl) {
                $event->setResponse(new RedirectResponse($failureUrl));

                $flashBag = $this->session->getFlashBag();
                if (!$flashBag->has('error')) {
                    $flashBag->add('error', $this->messageProvider->getErrorMessage($paymentTransaction));
                }
            }
        }
    }

    /**
     * Add paymentIntentId parameter to the payment transaction Options manually. This parameter value is used for
     * payment confirmation.
     */
    private function updateTransactionOptions(PaymentTransaction $paymentTransaction, array $eventData): void
    {
        $transactionOptions = $paymentTransaction->getTransactionOptions();

        $additionalOptions = json_decode($transactionOptions['additionalData'], true);
        if (array_key_exists('paymentIntentId', $eventData)) {
            $additionalOptions[PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM] = $eventData['paymentIntentId'];
        }
        if (array_key_exists('setupIntentId', $eventData)) {
            $additionalOptions[SetupIntentResponse::SETUP_INTENT_ID_PARAM] = $eventData['setupIntentId'];
        }
        $transactionOptions['additionalData'] = json_encode($additionalOptions);
        $paymentTransaction->setTransactionOptions($transactionOptions);
    }

    private function isPartiallySuccessfulResponse(array $responseData): bool
    {
        return !empty($responseData['is_multi_transaction']) && !empty($responseData['has_successful']);
    }

    private function getFailureUrl(PaymentTransaction $paymentTransaction): ?string
    {
        $transactionOptions = $paymentTransaction->getTransactionOptions();

        return $transactionOptions['failureUrl'] ?? null;
    }

    private function getPartiallyPaidUrl(PaymentTransaction $paymentTransaction): ?string
    {
        $transactionOptions = $paymentTransaction->getTransactionOptions();

        return $transactionOptions['partiallyPaidUrl'] ?? null;
    }
}
