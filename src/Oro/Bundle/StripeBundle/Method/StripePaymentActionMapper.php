<?php

namespace Oro\Bundle\StripeBundle\Method;

use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;

/**
 * Stripe API uses other identifiers to detect Authorize and capture payment actions. Class maps Stripe payments actions
 * with Payment actions used in ORO Application.
 */
class StripePaymentActionMapper
{
    public const MANUAL = 'manual';
    public const AUTOMATIC = 'automatic';

    private static array $actionsMap = [
        self::MANUAL => PaymentMethodInterface::AUTHORIZE,
        self::AUTOMATIC => PaymentMethodInterface::CAPTURE
    ];

    public static function getPaymentAction(string $paymentAction): string
    {
        if (!isset(self::$actionsMap[$paymentAction])) {
            throw new \LogicException(sprintf('Payment action "%s" is not supported by Stripe API', $paymentAction));
        }

        return self::$actionsMap[$paymentAction];
    }
}
