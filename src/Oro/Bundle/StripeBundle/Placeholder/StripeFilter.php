<?php

namespace Oro\Bundle\StripeBundle\Placeholder;

use Oro\Bundle\StripeBundle\Provider\StripeEnabledMonitoringCachedProvider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Placeholder Filter which determines whether Stripe.js external script should be included.
 */
class StripeFilter
{
    private RequestStack $requestStack;

    private StripeEnabledMonitoringCachedProvider $provider;

    /**
     * @var array<string>
     */
    private $allowedRoutes = [];

    public function __construct(RequestStack $requestStack, StripeEnabledMonitoringCachedProvider $provider)
    {
        $this->requestStack = $requestStack;
        $this->provider = $provider;
    }

    /**
     * @param string[] $allowedRoutes
     */
    public function setAllowedRoutes(array $allowedRoutes): void
    {
        $this->allowedRoutes = $allowedRoutes;
    }

    public function isApplicable(): bool
    {
        if (!$this->provider->isStripeEnabled()) {
            return false;
        }

        return $this->isRouteAllowed() || $this->provider->isStripeMonitoringEnabled();
    }

    private function isRouteAllowed(): bool
    {
        $request = $this->requestStack->getMainRequest();

        return $request && in_array($request->attributes->get('_route'), $this->allowedRoutes, true);
    }
}
