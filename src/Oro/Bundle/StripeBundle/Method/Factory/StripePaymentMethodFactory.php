<?php

namespace Oro\Bundle\StripeBundle\Method\Factory;

use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionRegistry;
use Oro\Bundle\StripeBundle\Method\StripePaymentMethod;
use Psr\Log\LoggerInterface;

/**
 * Creates StripePaymentMethod instance.
 */
class StripePaymentMethodFactory
{
    private LoggerInterface $logger;
    private PaymentActionRegistry $paymentActionRegistry;

    public function __construct(PaymentActionRegistry $paymentActionRegistry, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->paymentActionRegistry = $paymentActionRegistry;
    }

    public function create(StripePaymentConfig $config): StripePaymentMethod
    {
        $method = new StripePaymentMethod($config, $this->paymentActionRegistry);
        $method->setLogger($this->logger);

        return $method;
    }
}
