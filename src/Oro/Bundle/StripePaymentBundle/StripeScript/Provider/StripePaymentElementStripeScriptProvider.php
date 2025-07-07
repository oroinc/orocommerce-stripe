<?php

namespace Oro\Bundle\StripePaymentBundle\StripeScript\Provider;

use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Provides the Stripe script status and version depending on the User Monitoring state of Stripe Payment Element.
 */
class StripePaymentElementStripeScriptProvider implements StripeScriptProviderInterface
{
    public function __construct(
        private StripePaymentElementConfigProvider $stripePaymentElementConfigProvider,
        private FrontendHelper $frontendHelper,
        private TokenAccessorInterface $tokenAccessor,
        private CacheInterface $cache
    ) {
    }

    public static function getStripeScriptEnabledCacheKey(int $organizationId): string
    {
        return sprintf('stripe_script_enabled|%s', $organizationId);
    }

    public static function getStripeScriptVersionCacheKey(int $organizationId): string
    {
        return sprintf('stripe_script_version|%s', $organizationId);
    }

    #[\Override]
    public function isStripeScriptEnabled(): bool
    {
        if (!$this->frontendHelper->isFrontendRequest()) {
            return false;
        }

        return $this->cache->get(
            self::getStripeScriptEnabledCacheKey($this->tokenAccessor->getOrganizationId()),
            $this->doGetStripeScriptEnabled(...)
        );
    }


    private function doGetStripeScriptEnabled(): bool
    {
        foreach ($this->stripePaymentElementConfigProvider->getPaymentConfigs() as $stripePaymentElementConfig) {
            if ($stripePaymentElementConfig->isUserMonitoringEnabled()) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function getStripeScriptVersion(): string
    {
        return $this->cache->get(
            self::getStripeScriptVersionCacheKey($this->tokenAccessor->getOrganizationId()),
            $this->doGetStripeVersion(...)
        );
    }

    private function doGetStripeVersion(): string
    {
        foreach ($this->stripePaymentElementConfigProvider->getPaymentConfigs() as $stripePaymentElementConfig) {
            if ($stripePaymentElementConfig->isUserMonitoringEnabled()) {
                return $stripePaymentElementConfig->getScriptVersion();
            }
        }

        return '';
    }
}
