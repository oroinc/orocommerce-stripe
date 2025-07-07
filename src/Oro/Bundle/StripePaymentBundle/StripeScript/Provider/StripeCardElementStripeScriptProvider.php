<?php

namespace Oro\Bundle\StripePaymentBundle\StripeScript\Provider;

use Oro\Bundle\StripeBundle\Placeholder\StripeFilter;

/**
 * Adapter for {@see StripeFilter} from StripeBundle.
 */
class StripeCardElementStripeScriptProvider implements StripeScriptProviderInterface
{
    public function __construct(
        private StripeFilter $stripeFilter,
        private $stripeJsScriptVersion = 'v3'
    ) {
    }

    #[\Override]
    public function isStripeScriptEnabled(): bool
    {
        return $this->stripeFilter->isApplicable();
    }

    #[\Override]
    public function getStripeScriptVersion(): string
    {
        return $this->stripeJsScriptVersion;
    }
}
