<?php

namespace Oro\Bundle\StripeBundle\Placeholder;

use Oro\Bundle\StripeBundle\Provider\StripeEnabledMonitoringCachedProvider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Placeholder Filter which determines whether Stripe.js external script should be included.
 */
class StripeFilter
{
    private const CHECKOUT_ROUTE_NAME = 'oro_checkout_frontend_checkout';

    private RequestStack $requestStack;
    private StripeEnabledMonitoringCachedProvider $provider;

    public function __construct(RequestStack $requestStack, StripeEnabledMonitoringCachedProvider $provider)
    {
        $this->requestStack = $requestStack;
        $this->provider = $provider;
    }

    public function isApplicable(): bool
    {
        if (!$this->provider->isStripeEnabled()) {
            return false;
        }

        return $this->isCheckoutPage() || $this->provider->isStripeMonitoringEnabled();
    }

    private function isCheckoutPage(): bool
    {
        $request = $this->requestStack->getMainRequest();
        return $request->attributes->get('_route') === self::CHECKOUT_ROUTE_NAME;
    }
}
