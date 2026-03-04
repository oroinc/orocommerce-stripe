<?php

namespace Oro\Bundle\StripePaymentBundle\Layout\DataProvider;

use Oro\Bundle\CheckoutBundle\Entity\Checkout;

/**
 * The class is empty on purpose as it is not used anymore and exists only as BC layer to avoid errors in old themes
 * that rely on this data provider.
 */
class StripePaymentMethodsDataProvider
{
    public function getStripePaymentMethodNames(Checkout $checkout): array
    {
        return [];
    }

    public function getStripePaymentMethodsCount(Checkout $checkout): int
    {
        return 0;
    }
}
