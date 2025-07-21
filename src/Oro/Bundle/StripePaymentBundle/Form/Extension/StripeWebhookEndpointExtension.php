<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Form\Extension;

use Oro\Bundle\IntegrationBundle\Form\Type\ChannelType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Adds the event subscriber that handles the "webhookCreate" form field in the integration transport settings form.
 */
class StripeWebhookEndpointExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly EventSubscriberInterface $stripeWebhookEndpointEventSubscriber
    ) {
    }

    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        return [ChannelType::class];
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber($this->stripeWebhookEndpointEventSubscriber);
    }
}
