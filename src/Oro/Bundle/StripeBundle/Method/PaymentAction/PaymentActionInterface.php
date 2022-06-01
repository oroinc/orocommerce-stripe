<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Handle Stripe payment actions.
 */
interface PaymentActionInterface
{
    public const CONFIRM_ACTION = 'confirm';

    /**
     * Handle certain payment actions.
     */
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface;

    public function isApplicable(string $action): bool;
}
