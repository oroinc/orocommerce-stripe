<?php

namespace Oro\Bundle\StripeBundle\Method;

/**
 * Adds an Apple/Google Pay payment method
 */
class StripeAppleGooglePaymentMethod extends StripePaymentMethod
{
    public const METHOD_SUFFIX = '_apple_google_pay';

    #[\Override]
    public function getIdentifier(): string
    {
        return self::buildIdentifier(parent::getIdentifier());
    }

    public static function buildIdentifier(string $identifier): string
    {
        return $identifier . self::METHOD_SUFFIX;
    }
}
