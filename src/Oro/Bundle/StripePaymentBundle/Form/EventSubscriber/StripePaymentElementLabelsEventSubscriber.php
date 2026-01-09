<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Form\EventSubscriber;

use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sets default name and labels for the Stripe Payment Element settings form.
 */
class StripePaymentElementLabelsEventSubscriber implements EventSubscriberInterface
{
    private string $defaultName = 'oro.stripe_payment.payment_element.label';
    private string $defaultLabel = 'oro.stripe_payment.payment_element.payment_method_labels.default';
    private string $defaultShortLabel = 'oro.stripe_payment.payment_element.payment_method_short_labels.default';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function setDefaultName(string $defaultName): void
    {
        $this->defaultName = $defaultName;
    }

    public function setDefaultLabel(string $defaultLabel): void
    {
        $this->defaultLabel = $defaultLabel;
    }

    public function setDefaultShortLabel(string $defaultShortLabel): void
    {
        $this->defaultShortLabel = $defaultShortLabel;
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
        ];
    }

    public function onPreSetData(FormEvent $event): void
    {
        /** @var StripePaymentElementSettings|null $stripePaymentElementSettings */
        $stripePaymentElementSettings = $event->getData();

        if ($stripePaymentElementSettings === null) {
            return;
        }

        $defaultName = $this->translator->trans($this->defaultName);

        $form = $event->getForm();
        $channelNameField = $form->getParent()->get('name');
        $channelName = $channelNameField->getData();
        if ($channelName === '' || $channelName === null) {
            $channelNameField->setData($defaultName);
        }

        if (
            $stripePaymentElementSettings->getPaymentMethodName() === '' ||
            $stripePaymentElementSettings->getPaymentMethodName() === null
        ) {
            $stripePaymentElementSettings->setPaymentMethodName($defaultName);
        }

        if ($stripePaymentElementSettings->getPaymentMethodLabels()->isEmpty()) {
            $stripePaymentElementSettings->addPaymentMethodLabel($this->createLabel($this->defaultLabel));
        }

        if ($stripePaymentElementSettings->getPaymentMethodShortLabels()->isEmpty()) {
            $stripePaymentElementSettings->addPaymentMethodShortLabel($this->createLabel($this->defaultShortLabel));
        }
    }

    private function createLabel(string $translationKey): LocalizedFallbackValue
    {
        return (new LocalizedFallbackValue())->setString($this->translator->trans($translationKey));
    }
}
