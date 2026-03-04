<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeScript\Provider;

use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Provides the Stripe script status and version for specific routes.
 */
class StripePaymentElementRouteAwareStripeScriptProvider implements StripeScriptProviderInterface
{
    /**
     * @var array<string> List of allowed routes where the Stripe script must be enabled.
     */
    private array $allowedRoutes = [];

    public function __construct(
        private StripePaymentElementConfigProvider $stripePaymentElementConfigProvider,
        private RequestStack $requestStack,
        private TokenAccessorInterface $tokenAccessor,
        private CacheInterface $cache
    ) {
    }

    public function setAllowedRoutes(array $allowedRoutes): void
    {
        $this->allowedRoutes = $allowedRoutes;
    }

    public static function getStripeScriptEnabledCacheKey(int $organizationId): string
    {
        return sprintf('stripe_script_enabled_on_route|%s', $organizationId);
    }

    public static function getStripeScriptVersionCacheKey(int $organizationId): string
    {
        return sprintf('stripe_script_version_on_route|%s', $organizationId);
    }

    #[\Override]
    public function isStripeScriptEnabled(): bool
    {
        $request = $this->requestStack->getMainRequest();
        if (!$request) {
            return false;
        }

        if (!in_array($request->attributes->get('_route'), $this->allowedRoutes, true)) {
            return false;
        }

        return $this->cache->get(
            self::getStripeScriptEnabledCacheKey((int) $this->tokenAccessor->getOrganizationId()),
            $this->doGetStripeEnabled(...)
        );
    }

    private function doGetStripeEnabled(): bool
    {
        return count($this->stripePaymentElementConfigProvider->getPaymentConfigs()) > 0;
    }

    #[\Override]
    public function getStripeScriptVersion(): string
    {
        return $this->cache->get(
            self::getStripeScriptVersionCacheKey((int) $this->tokenAccessor->getOrganizationId()),
            $this->doGetStripeVersion(...)
        );
    }

    private function doGetStripeVersion(): string
    {
        foreach ($this->stripePaymentElementConfigProvider->getPaymentConfigs() as $stripePaymentElementConfig) {
            return $stripePaymentElementConfig->getScriptVersion();
        }

        return '';
    }
}
