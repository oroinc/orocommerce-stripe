<?php

namespace Oro\Bundle\StripeBundle\Method;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Provides basic data and logic to handle payments with Stripe Payment System.
 */
class StripePaymentMethod implements PaymentMethodInterface
{
    private const MINIMAL_AMOUNT_TO_ORDER = 0.5;

    private StripePaymentConfig $config;
    private PaymentActionRegistry $paymentActionRegistry;
    private LoggerInterface $logger;

    public function __construct(StripePaymentConfig $config, PaymentActionRegistry $paymentActionRegistry)
    {
        $this->config = $config;
        $this->paymentActionRegistry = $paymentActionRegistry;
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function execute($action, PaymentTransaction $paymentTransaction): array
    {
        try {
            $response = $this->paymentActionRegistry->getPaymentAction($action, $paymentTransaction)
                ->execute($this->config, $paymentTransaction);
            return $response->prepareResponse();
        } catch (StripeApiException $stripeException) {
            $this->logger->error($stripeException->getMessage(), [
                'error' => $stripeException->getMessage(),
                'stripe_error_code' => $stripeException->getStripeErrorCode(),
                'decline_code' => $stripeException->getDeclineCode(),
                'exception' => $stripeException
            ]);

            return [
                'successful' => false,
                'error' => $stripeException->getMessage(),
                'message' => $stripeException->getMessage(),
                'stripe_error_code' => $stripeException->getStripeErrorCode(),
                'decline_code' => $stripeException->getDeclineCode()
            ];
        }
    }

    public function getIdentifier(): string
    {
        return $this->config->getPaymentMethodIdentifier();
    }

    /**
     * According to the documentation minimal amount to order should be greater than 0.5
     * @see https://stripe.com/docs/api/payment_intents/object#payment_intent_object-amount.
     *
     * @param PaymentContextInterface $context
     * @return bool
     */
    public function isApplicable(PaymentContextInterface $context): bool
    {
        return $context->getTotal() >= self::MINIMAL_AMOUNT_TO_ORDER;
    }

    public function supports($actionName): bool
    {
        return in_array($actionName, [
            PaymentMethodInterface::PURCHASE,
            PaymentActionInterface::CONFIRM_ACTION,
            PaymentMethodInterface::CAPTURE,
            PaymentMethodInterface::CANCEL,
            PaymentMethodInterface::REFUND
        ]);
    }
}
