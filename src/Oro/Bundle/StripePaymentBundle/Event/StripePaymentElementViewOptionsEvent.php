<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Event;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;

/**
 * Dispatched when the Stripe Payment Element payment method gathers view options before rendering.
 */
class StripePaymentElementViewOptionsEvent
{
    public function __construct(
        private readonly PaymentContextInterface $paymentContext,
        private readonly StripePaymentElementConfig $stripePaymentElementConfig,
        private array $viewOptions
    ) {
    }

    public function getPaymentContext(): PaymentContextInterface
    {
        return $this->paymentContext;
    }

    public function getStripePaymentElementConfig(): StripePaymentElementConfig
    {
        return $this->stripePaymentElementConfig;
    }

    public function getViewOptions(): array
    {
        return $this->viewOptions;
    }

    public function setViewOptions(array $viewOptions): void
    {
        $this->viewOptions = $viewOptions;
    }

    public function addViewOption(string $name, mixed $value): void
    {
        $this->viewOptions[$name] = $value;
    }

    public function getViewOption(string $name): mixed
    {
        return $this->viewOptions[$name] ?? null;
    }
}
