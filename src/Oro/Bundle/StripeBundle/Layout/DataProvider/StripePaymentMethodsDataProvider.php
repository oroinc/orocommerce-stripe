<?php

namespace Oro\Bundle\StripeBundle\Layout\DataProvider;

use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Provider\CheckoutPaymentContextProvider;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\ApplicablePaymentMethodsProvider;
use Oro\Bundle\StripeBundle\Method\Provider\StripePaymentMethodsProvider;
use Oro\Bundle\StripeBundle\Method\StripeAppleGooglePaymentMethod;

/**
 * Provides the number of applicable Stripe card payment methods
 * (excluding Apple Pay/Google Pay methods)
 */
class StripePaymentMethodsDataProvider
{
    private CheckoutPaymentContextProvider $checkoutPaymentContextProvider;
    private ApplicablePaymentMethodsProvider $applicablePaymentMethodsProvider;
    private StripePaymentMethodsProvider $stripePaymentMethodsProvider;

    public function __construct(
        CheckoutPaymentContextProvider $checkoutPaymentContextProvider,
        ApplicablePaymentMethodsProvider $applicablePaymentMethodsProvider,
        StripePaymentMethodsProvider $stripePaymentMethodsProvider
    ) {
        $this->checkoutPaymentContextProvider = $checkoutPaymentContextProvider;
        $this->applicablePaymentMethodsProvider = $applicablePaymentMethodsProvider;
        $this->stripePaymentMethodsProvider = $stripePaymentMethodsProvider;
    }

    public function getStripePaymentMethodsCount(Checkout $checkout): int
    {
        $paymentContext = $this->checkoutPaymentContextProvider->getContext($checkout);
        $applicableMethods = $this->applicablePaymentMethodsProvider->getApplicablePaymentMethods($paymentContext);

        $stripeMethods = $this->stripePaymentMethodsProvider->getPaymentMethods();

        $stripeCardMethods = array_filter(
            $stripeMethods,
            static fn (PaymentMethodInterface $method) => !$method instanceof StripeAppleGooglePaymentMethod
        );

        $applicableStripeCardMethods = array_intersect_key($applicableMethods, $stripeCardMethods);

        return count($applicableStripeCardMethods);
    }
}
