<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * PaymentIntent is Stripe object used for payment information storage. Different response types could have its own
 * relations with paymentIntent.
 */
interface PaymentIntentAwareInterface
{
    public function getPaymentIntentId(): string;
}
