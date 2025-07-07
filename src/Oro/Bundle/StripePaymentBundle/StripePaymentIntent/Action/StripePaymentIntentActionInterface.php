<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;

/**
 * Interface for the Stripe PaymentIntents API action model.
 */
interface StripePaymentIntentActionInterface
{
    public const string PAYMENT_INTENT_ID = 'stripePaymentIntentId';
    public const string PAYMENT_METHOD_ID = 'stripePaymentMethodId';
    public const string CUSTOMER_ID = 'stripeCustomerId';
    public const string REFUND_ID = 'stripeRefundId';
    public const string REFUND_REASON = 'refundReason';
    public const string CANCEL_REASON = 'cancelReason';

    public function getActionName(): string;

    public function getStripeClientConfig(): StripeClientConfigInterface;

    public function getPaymentTransaction(): PaymentTransaction;

    public function getPaymentIntentConfig(): StripePaymentIntentConfigInterface;
}
