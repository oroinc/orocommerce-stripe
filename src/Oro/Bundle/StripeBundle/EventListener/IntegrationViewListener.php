<?php

namespace Oro\Bundle\StripeBundle\EventListener;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\StripeBundle\Integration\StripeChannelType;
use Oro\Bundle\UIBundle\Event\BeforeListRenderEvent;

/**
 * Adds a note to the end of Stripe integration edit form
 */
class IntegrationViewListener
{
    public function onIntegrationEdit(BeforeListRenderEvent $event): void
    {
        $entity = $event->getEntity();

        if (!$entity instanceof Channel || $entity->getType() !== StripeChannelType::TYPE) {
            return;
        }

        $scrollData = $event->getScrollData();
        $scrollData->addSubBlockData(
            0,
            0,
            $event->getEnvironment()->render('@OroStripe/Form/integrationNote.html.twig')
        );
    }
}
