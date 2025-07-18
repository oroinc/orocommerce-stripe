<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Form\Extension;

use Oro\Bundle\IntegrationBundle\Form\Type\ChannelType;
use Oro\Bundle\StripePaymentBundle\Form\Extension\StripeWebhookEndpointExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormBuilderInterface;

final class StripeWebhookEndpointExtensionTest extends TestCase
{
    private StripeWebhookEndpointExtension $extension;

    private MockObject&EventSubscriberInterface $eventSubscriber;

    protected function setUp(): void
    {
        $this->eventSubscriber = $this->createMock(EventSubscriberInterface::class);

        $this->extension = new StripeWebhookEndpointExtension($this->eventSubscriber);
    }

    public function testGetExtendedTypes(): void
    {
        self::assertEquals([ChannelType::class], iterator_to_array($this->extension::getExtendedTypes()));
    }

    public function testBuildFormAddsEventSubscriberToFormBuilder(): void
    {
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formBuilder
            ->expects(self::once())
            ->method('addEventSubscriber')
            ->with($this->eventSubscriber);

        $this->extension->buildForm($formBuilder, []);
    }
}
