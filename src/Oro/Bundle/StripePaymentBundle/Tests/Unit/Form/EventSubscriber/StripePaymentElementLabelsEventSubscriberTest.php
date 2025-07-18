<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Form\EventSubscriber;

use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\Form\EventSubscriber\StripePaymentElementLabelsEventSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class StripePaymentElementLabelsEventSubscriberTest extends TestCase
{
    private StripePaymentElementLabelsEventSubscriber $subscriber;

    private MockObject&TranslatorInterface $translator;

    private MockObject&FormInterface $form;

    private MockObject&FormInterface $parentForm;

    private MockObject&FormInterface $channelNameField;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->subscriber = new StripePaymentElementLabelsEventSubscriber($this->translator);

        $this->form = $this->createMock(FormInterface::class);
        $this->parentForm = $this->createMock(FormInterface::class);
        $this->channelNameField = $this->createMock(FormInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $expected = [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
        ];

        self::assertSame($expected, StripePaymentElementLabelsEventSubscriber::getSubscribedEvents());
    }

    public function testOnPreSetDataWithNullData(): void
    {
        $event = new FormEvent($this->form, null);

        $this->form
            ->expects(self::never())
            ->method('getParent');

        $this->subscriber->onPreSetData($event);
    }

    public function testOnPreSetDataWithEmptySettings(): void
    {
        $settings = new StripePaymentElementSettings();
        $translatedName = 'Translated Name';
        $translatedLabel = 'Translated Label';
        $translatedShortLabel = 'Translated Short Label';

        $this->translator
            ->expects(self::exactly(3))
            ->method('trans')
            ->willReturnMap([
                [
                    'oro.stripe_payment.payment_element.label',
                    [],
                    null,
                    null,
                    $translatedName,
                ],
                [
                    'oro.stripe_payment.payment_element.payment_method_labels.default',
                    [],
                    null,
                    null,
                    $translatedLabel,
                ],
                [
                    'oro.stripe_payment.payment_element.payment_method_short_labels.default',
                    [],
                    null,
                    null,
                    $translatedShortLabel,
                ],
            ]);

        $this->form
            ->expects(self::once())
            ->method('getParent')
            ->willReturn($this->parentForm);

        $this->parentForm
            ->expects(self::once())
            ->method('get')
            ->with('name')
            ->willReturn($this->channelNameField);

        $this->channelNameField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(null);

        $this->channelNameField
            ->expects(self::once())
            ->method('setData')
            ->with($translatedName);

        $event = new FormEvent($this->form, $settings);
        $this->subscriber->onPreSetData($event);

        self::assertSame($translatedName, $settings->getPaymentMethodName());
        self::assertCount(1, $settings->getPaymentMethodLabels());
        self::assertSame($translatedLabel, $settings->getPaymentMethodLabels()->first()->getString());
        self::assertCount(1, $settings->getPaymentMethodShortLabels());
        self::assertSame($translatedShortLabel, $settings->getPaymentMethodShortLabels()->first()->getString());
    }

    public function testOnPreSetDataWithExistingChannelName(): void
    {
        $settings = new StripePaymentElementSettings();
        $existingChannelName = 'Existing Channel';

        $this->form
            ->expects(self::once())
            ->method('getParent')
            ->willReturn($this->parentForm);

        $this->parentForm
            ->expects(self::once())
            ->method('get')
            ->with('name')
            ->willReturn($this->channelNameField);

        $this->channelNameField
            ->expects(self::once())
            ->method('getData')
            ->willReturn($existingChannelName);

        $this->channelNameField
            ->expects(self::never())
            ->method('setData');

        $event = new FormEvent($this->form, $settings);
        $this->subscriber->onPreSetData($event);
    }

    public function testOnPreSetDataWithExistingPaymentMethodName(): void
    {
        $settings = new StripePaymentElementSettings();
        $existingName = 'Existing Name';
        $settings->setPaymentMethodName($existingName);

        $this->form
            ->expects(self::once())
            ->method('getParent')
            ->willReturn($this->parentForm);

        $this->parentForm
            ->expects(self::once())
            ->method('get')
            ->with('name')
            ->willReturn($this->channelNameField);

        $this->channelNameField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(null);

        $event = new FormEvent($this->form, $settings);
        $this->subscriber->onPreSetData($event);

        self::assertSame($existingName, $settings->getPaymentMethodName());
    }

    public function testOnPreSetDataWithExistingLabels(): void
    {
        $settings = new StripePaymentElementSettings();
        $existingLabel = new LocalizedFallbackValue();
        $existingLabel->setString('Existing Label');
        $settings->addPaymentMethodLabel($existingLabel);

        $existingShortLabel = new LocalizedFallbackValue();
        $existingShortLabel->setString('Existing Short Label');
        $settings->addPaymentMethodShortLabel($existingShortLabel);

        $this->form
            ->expects(self::once())
            ->method('getParent')
            ->willReturn($this->parentForm);

        $this->parentForm
            ->expects(self::once())
            ->method('get')
            ->with('name')
            ->willReturn($this->channelNameField);

        $this->channelNameField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(null);

        $event = new FormEvent($this->form, $settings);
        $this->subscriber->onPreSetData($event);

        self::assertCount(1, $settings->getPaymentMethodLabels());
        self::assertSame('Existing Label', $settings->getPaymentMethodLabels()->first()->getString());
        self::assertCount(1, $settings->getPaymentMethodShortLabels());
        self::assertSame('Existing Short Label', $settings->getPaymentMethodShortLabels()->first()->getString());
    }

    public function testOnPreSetDataWithCustomLabels(): void
    {
        $settings = new StripePaymentElementSettings();
        $customName = 'custom.name';
        $customLabel = 'custom.label';
        $customShortLabel = 'custom.short_label';

        $translatedName = 'Custom Translated Name';
        $translatedLabel = 'Custom Translated Label';
        $translatedShortLabel = 'Custom Translated Short Label';

        $this->subscriber->setDefaultName($customName);
        $this->subscriber->setDefaultLabel($customLabel);
        $this->subscriber->setDefaultShortLabel($customShortLabel);

        $this->translator
            ->expects(self::exactly(3))
            ->method('trans')
            ->willReturnMap([
                [$customName, [], null, null, $translatedName],
                [$customLabel, [], null, null, $translatedLabel],
                [$customShortLabel, [], null, null, $translatedShortLabel],
            ]);

        $this->form
            ->expects(self::once())
            ->method('getParent')
            ->willReturn($this->parentForm);

        $this->parentForm
            ->expects(self::once())
            ->method('get')
            ->with('name')
            ->willReturn($this->channelNameField);

        $this->channelNameField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(null);

        $event = new FormEvent($this->form, $settings);
        $this->subscriber->onPreSetData($event);

        self::assertSame($translatedName, $settings->getPaymentMethodName());
        self::assertCount(1, $settings->getPaymentMethodLabels());
        self::assertSame($translatedLabel, $settings->getPaymentMethodLabels()->first()->getString());
        self::assertCount(1, $settings->getPaymentMethodShortLabels());
        self::assertSame($translatedShortLabel, $settings->getPaymentMethodShortLabels()->first()->getString());
    }
}
