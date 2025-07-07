<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement;

use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeAmountValidator\StripeAmountValidatorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Creates an instance of the Stripe Payment Element payment method.
 */
class StripePaymentElementMethodFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripePaymentIntentActionExecutorInterface $stripePaymentActionExecutor,
        private readonly StripeAmountValidatorInterface $stripeAmountValidator,
        private readonly PaymentTransactionProvider $paymentTransactionProvider
    ) {
        $this->logger = new NullLogger();
    }

    /**
     * @param StripePaymentElementConfig $stripePaymentElementConfig
     * @param array<string> $paymentMethodGroups Payment method groups the payment method applicable for.
     *
     * @return PaymentMethodInterface
     */
    public function create(
        StripePaymentElementConfig $stripePaymentElementConfig,
        array $paymentMethodGroups
    ): PaymentMethodInterface {
        $paymentMethod = new StripePaymentElementMethod(
            $stripePaymentElementConfig,
            $this->stripePaymentActionExecutor,
            $this->stripeAmountValidator,
            $this->paymentTransactionProvider,
            $paymentMethodGroups
        );
        $paymentMethod->setLogger($this->logger);

        return $paymentMethod;
    }
}
