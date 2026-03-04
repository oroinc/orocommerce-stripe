<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\EventListener;

use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Provider\MultiShipping\ConfigProvider;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentElementViewOptionsEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\View\StripePaymentElementMethodView;

/**
 * Adds setupFutureUsage = 'off_session' to Stripe Payment Element view options if the order has sub-orders.
 *
 * @link https://docs.stripe.com/api/payment_intents/object#payment_intent_object-setup_future_usage
 */
final class AddSetupFutureUsageToStripePaymentElementViewOptionsListener
{
    public function __construct(
        private readonly ConfigProvider $multiShippingConfigProvider
    ) {
    }

    public function onStripePaymentElementViewOptions(StripePaymentElementViewOptionsEvent $event): void
    {
        if (!$this->shouldAddSetupFutureUsage($event)) {
            return;
        }

        $viewOptions = $event->getViewOptions();
        $viewOptions[StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS]['setupFutureUsage']
            = 'off_session';

        $event->setViewOptions($viewOptions);
    }

    private function shouldAddSetupFutureUsage(StripePaymentElementViewOptionsEvent $event): bool
    {
        $viewOption = $event->getViewOption(StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS);
        if (!empty($viewOption['setupFutureUsage'])) {
            return false;
        }

        $source = $event->getPaymentContext()->getSourceEntity();
        if (!$source instanceof Checkout) {
            return false;
        }

        return $this->multiShippingConfigProvider->isCreateSubOrdersForEachGroupEnabled();
    }
}
