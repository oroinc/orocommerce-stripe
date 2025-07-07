<?php

namespace Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\View;

use Oro\Bundle\CurrencyBundle\DependencyInjection\Configuration as CurrencyConfiguration;
use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Method\View\PaymentMethodViewInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentElementViewOptionsEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeScriptEnabledProvider;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Represents the Stripe Payment Element payment method view.
 */
class StripePaymentElementMethodView implements PaymentMethodViewInterface
{
    public const string STRIPE_OPTIONS = 'stripeOptions';
    public const string STRIPE_ELEMENTS_OBJECT_OPTIONS = 'stripeElementsObjectOptions';
    public const string STRIPE_PAYMENT_ELEMENT_OPTIONS = 'stripePaymentElementOptions';

    public function __construct(
        private readonly StripePaymentElementConfig $stripePaymentElementConfig,
        private readonly StripeScriptEnabledProvider $stripeScriptEnabledProvider,
        private readonly StripeAmountConverterInterface $stripeAmountConverter,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[\Override]
    public function getAdminLabel(): string
    {
        return $this->stripePaymentElementConfig->getAdminLabel();
    }

    #[\Override]
    public function getLabel(): string
    {
        return $this->stripePaymentElementConfig->getLabel();
    }

    #[\Override]
    public function getShortLabel(): string
    {
        return $this->stripePaymentElementConfig->getShortLabel();
    }

    #[\Override]
    public function getPaymentMethodIdentifier(): string
    {
        return $this->stripePaymentElementConfig->getPaymentMethodIdentifier();
    }

    #[\Override]
    public function getBlock(): string
    {
        return 'oro_stripe_payment_element_widget';
    }

    #[\Override]
    public function getOptions(PaymentContextInterface $context): array
    {
        $this->stripeScriptEnabledProvider->enableStripeScript($this->stripePaymentElementConfig->getScriptVersion());

        $stripeCurrency = \strtolower($context->getCurrency() ?? CurrencyConfiguration::DEFAULT_CURRENCY);
        $stripeAmount = $this->stripeAmountConverter->convertToStripeFormat(
            (float)$context->getTotal(),
            $stripeCurrency
        );

        $options = [
            // @link https://docs.stripe.com/js/initializing
            self::STRIPE_OPTIONS => [
                'apiPublicKey' => $this->stripePaymentElementConfig->getApiPublicKey(),
                'locale' => $this->stripePaymentElementConfig->getLocale(),
            ],
            // @link https://docs.stripe.com/js/elements_object/create_without_intent
            self::STRIPE_ELEMENTS_OBJECT_OPTIONS => [
                'amount' => $stripeAmount,
                'currency' => $stripeCurrency,
                'mode' => 'payment',
                // Set to "automatic" to display all available payment methods regardless of manual capture capability.
                'captureMethod' => 'automatic',
            ],
            // @link https://docs.stripe.com/js/element/payment_element
            self::STRIPE_PAYMENT_ELEMENT_OPTIONS => [],
        ];

        if ($this->stripePaymentElementConfig->isReAuthorizationEnabled()) {
            $options[self::STRIPE_ELEMENTS_OBJECT_OPTIONS]['setupFutureUsage'] = 'off_session';
        }

        $event = new StripePaymentElementViewOptionsEvent($context, $this->stripePaymentElementConfig, $options);
        $this->eventDispatcher->dispatch($event);

        return $event->getViewOptions();
    }
}
