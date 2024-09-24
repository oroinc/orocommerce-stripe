<?php

namespace Oro\Bundle\StripeBundle\Method\Provider;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\ApplicablePaymentMethodsProvider;
use Oro\Bundle\StripeBundle\Method\StripeAppleGooglePaymentMethod;

/**
 * Get sorted available payment methods provider. Sorting should be based on the requirement that Google/Apple Pay
 * methods should be displayed first.
 */
class SortedApplicablePaymentMethodsProvider extends ApplicablePaymentMethodsProvider
{
    #[\Override]
    protected function getActualApplicablePaymentMethods(PaymentContextInterface $context): array
    {
        $applicablePaymentMethods = parent::getActualApplicablePaymentMethods($context);

        uksort($applicablePaymentMethods, function ($a, $b) {
            // Google/Apple Pay should be the first in the payments methods list.
            if (str_ends_with($a, StripeAppleGooglePaymentMethod::METHOD_SUFFIX)
                || str_ends_with($b, StripeAppleGooglePaymentMethod::METHOD_SUFFIX)) {
                return 1;
            }

            return 0;
        });

        return $applicablePaymentMethods;
    }
}
