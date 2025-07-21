<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Form\EventSubscriber;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\Form\EventSubscriber\StripeWebhookEndpointEventSubscriber;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigFactory;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\CreateOrUpdateStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\DeleteStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor\StripeWebhookEndpointActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResult;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Bundle\UIBundle\Tools\UrlHelper;
use Oro\Component\ConfigExpression\Condition\NotBlank;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\WebhookEndpoint as StripeWebhookEndpoint;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class StripeWebhookEndpointEventSubscriberTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private const string CHANNEL_TYPE = 'stripe_payment_element';
    private const string WEBHOOK_STRIPE_ID = 'wh_123';
    private const string WEBHOOK_SECRET = 'wh_123_secret';
    private const string WEBHOOK_URL = 'https://example.com/webhook';

    private StripeWebhookEndpointEventSubscriber $subscriber;

    private MockObject&StripePaymentElementConfigFactory $stripePaymentElementConfigFactory;

    private MockObject&StripeWebhookEndpointActionExecutorInterface $stripeWebhookEndpointActionExecutor;

    private MockObject&UrlHelper $urlHelper;

    private MockObject&TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfigFactory = $this->createMock(StripePaymentElementConfigFactory::class);
        $this->stripeWebhookEndpointActionExecutor = $this->createMock(
            StripeWebhookEndpointActionExecutorInterface::class
        );
        $this->urlHelper = $this->createMock(UrlHelper::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->subscriber = new StripeWebhookEndpointEventSubscriber(
            $this->stripePaymentElementConfigFactory,
            $this->stripeWebhookEndpointActionExecutor,
            $this->urlHelper,
            $this->translator,
            self::CHANNEL_TYPE
        );

        $this->setUpLoggerMock($this->subscriber);
    }

    public function testGetSubscribedEvents(): void
    {
        $expectedEvents = [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
            FormEvents::POST_SUBMIT => 'onPostSubmit',
        ];

        self::assertSame($expectedEvents, StripeWebhookEndpointEventSubscriber::getSubscribedEvents());
    }

    // PreSetData event tests
    public function testOnPreSetDataWithNullChannel(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form
            ->expects(self::never())
            ->method('get');

        $event = new PreSetDataEvent($form, null);

        $this->subscriber->onPreSetData($event);
    }

    public function testOnPreSetDataWithNonApplicableChannelType(): void
    {
        $channel = new Channel();
        $channel->setType('non_applicable_type');

        $form = $this->createMock(FormInterface::class);
        $form
            ->expects(self::never())
            ->method('add');

        $event = new PreSetDataEvent($form, $channel);

        $this->subscriber->onPreSetData($event);
    }

    public function testOnPreSetDataWithNewStripeSettingsAndNonLocalUrl(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        $channel->setTransport($stripePaymentElementSettings);

        $this->urlHelper
            ->expects(self::once())
            ->method('isLocalUrl')
            ->willReturn(false);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('add')
            ->with(
                'webhookCreate',
                CheckboxType::class,
                [
                    'data' => true,
                    'required' => false,
                    'mapped' => false,
                    'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                    'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                    'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
                ]
            );

        $event = new PreSetDataEvent($form, $channel);

        $this->subscriber->onPreSetData($event);
    }

    public function testOnPreSetDataWithNewStripeSettingsAndLocalUrl(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        $channel->setTransport($stripePaymentElementSettings);

        $this->urlHelper
            ->expects(self::once())
            ->method('isLocalUrl')
            ->willReturn(true);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('add')
            ->with(
                'webhookCreate',
                CheckboxType::class,
                [
                    'data' => false,
                    'required' => false,
                    'mapped' => false,
                    'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                    'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                    'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
                ]
            );

        $event = new PreSetDataEvent($form, $channel);

        $this->subscriber->onPreSetData($event);
    }

    public function testOnPreSetDataWithExistingStripeSettingsAndStripeId(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings, 42);
        $stripePaymentElementSettings->setWebhookStripeId(self::WEBHOOK_STRIPE_ID);
        $channel->setTransport($stripePaymentElementSettings);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('add')
            ->with(
                'webhookCreate',
                CheckboxType::class,
                [
                    'data' => true,
                    'required' => false,
                    'mapped' => false,
                    'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                    'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                    'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
                ]
            );

        $event = new PreSetDataEvent($form, $channel);

        $this->subscriber->onPreSetData($event);
    }

    public function testOnPreSetDataWithExistingStripeSettingsAndNoStripeId(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings, 42);
        $channel->setTransport($stripePaymentElementSettings);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('add')
            ->with(
                'webhookCreate',
                CheckboxType::class,
                [
                    'data' => false,
                    'required' => false,
                    'mapped' => false,
                    'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                    'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                    'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
                ]
            );

        $event = new PreSetDataEvent($form, $channel);

        $this->subscriber->onPreSetData($event);
    }

    // PreSubmit event tests
    public function testOnPreSubmitWithNonApplicableChannelType(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form
            ->expects(self::never())
            ->method('get');

        $event = new PreSubmitEvent($form, ['type' => 'non_applicable_type']);

        $this->subscriber->onPreSubmit($event);
    }

    public function testOnPreSubmitWithWebhookCreateEnabled(): void
    {
        $transportForm = $this->createMock(FormInterface::class);

        $form = $this->createMock(FormInterface::class);
        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $webhookSecretField = $this->createMock(FormInterface::class);
        $transportForm
            ->expects(self::once())
            ->method('get')
            ->with('webhookSecret')
            ->willReturn($webhookSecretField);

        $webhookSecretFormConfig = $this->createMock(FormConfigInterface::class);
        $webhookSecretField
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn($webhookSecretFormConfig);

        $webhookSecretFormConfig
            ->expects(self::once())
            ->method('getOptions')
            ->willReturn(['constraints' => [new NotBlank()]]);

        $webhookSecretResolvedFormType = $this->createMock(ResolvedFormTypeInterface::class);
        $webhookSecretFormConfig
            ->expects(self::once())
            ->method('getType')
            ->willReturn($webhookSecretResolvedFormType);

        $webhookSecretResolvedFormType
            ->expects(self::once())
            ->method('getInnerType')
            ->willReturn(new TextType());

        $transportForm
            ->expects(self::exactly(2))
            ->method('add')
            ->willReturnMap([
                [
                    'webhookCreate',
                    CheckboxType::class,
                    [
                        'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                        'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                        'required' => false,
                        'mapped' => false,
                        'data' => false,
                        'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
                    ],
                    $transportForm,
                ],
                [
                    'webhookSecret',
                    TextType::class,
                    [
                        'data' => '',
                    ],
                    $transportForm,
                ],
            ]);

        $event = new PreSubmitEvent(
            $form,
            ['type' => self::CHANNEL_TYPE, 'transport' => ['webhookCreate' => true]]
        );

        $this->subscriber->onPreSubmit($event);
    }

    public function testOnPreSubmitWithWebhookCreateDisabled(): void
    {
        $transportForm = $this->createMock(FormInterface::class);

        $form = $this->createMock(FormInterface::class);
        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('add')
            ->with(
                'webhookCreate',
                CheckboxType::class,
                [
                    'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                    'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                    'required' => false,
                    'mapped' => false,
                    'data' => false,
                    'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
                ]
            );

        $event = new PreSubmitEvent(
            $form,
            ['type' => self::CHANNEL_TYPE, 'transport' => ['webhookCreate' => false]]
        );

        $this->subscriber->onPreSubmit($event);
    }

    // PostSubmit event tests
    public function testOnPostSubmitWithNullChannel(): void
    {
        $form = $this->createMock(FormInterface::class);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::never())
            ->method(self::anything());

        $event = new PostSubmitEvent($form, null);

        $this->subscriber->onPostSubmit($event);
    }

    public function testOnPostSubmitWithNonApplicableChannelType(): void
    {
        $channel = new Channel();
        $channel->setType('non_applicable_type');

        $form = $this->createMock(FormInterface::class);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::never())
            ->method(self::anything());

        $event = new PostSubmitEvent($form, $channel);

        $this->subscriber->onPostSubmit($event);
    }

    public function testOnPostSubmitWithNullTransport(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $form = $this->createMock(FormInterface::class);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::never())
            ->method(self::anything());

        $event = new PostSubmitEvent($form, $channel);

        $this->subscriber->onPostSubmit($event);
    }

    public function testOnPostSubmitWithInvalidForm(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        $channel->setTransport($stripePaymentElementSettings);

        $form = $this->createMock(FormInterface::class);
        $form
            ->expects(self::once())
            ->method('isValid')
            ->willReturn(false);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::never())
            ->method(self::anything());

        $event = new PostSubmitEvent($form, $channel);

        $this->subscriber->onPostSubmit($event);
    }

    public function testOnPostSubmitWithWebhookCreateDisabled(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        $stripePaymentElementSettings->setWebhookStripeId(self::WEBHOOK_STRIPE_ID);
        $channel->setTransport($stripePaymentElementSettings);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);
        $webhookCreateField = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('get')
            ->with('webhookCreate')
            ->willReturn($webhookCreateField);

        $webhookCreateField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(false);

        $stripeWebhookEndpointConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripeWebhookEndpointConfig);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(new DeleteStripeWebhookEndpointAction($stripeWebhookEndpointConfig))
            ->willReturn(new StripeWebhookEndpointActionResult(successful: true));

        $event = new PostSubmitEvent($form, $channel);

        $this->subscriber->onPostSubmit($event);

        self::assertNull($stripePaymentElementSettings->getWebhookStripeId());
    }

    public function testOnPostSubmitWithWebhookCreateDisabledAndNotSuccessful(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        $stripePaymentElementSettings->setWebhookStripeId(self::WEBHOOK_STRIPE_ID);
        $channel->setTransport($stripePaymentElementSettings);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);
        $webhookCreateField = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('get')
            ->with('webhookCreate')
            ->willReturn($webhookCreateField);

        $webhookCreateField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(false);

        $stripeWebhookEndpointConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripeWebhookEndpointConfig);

        $errorMessage = 'An error occurred while deleting the webhook.';
        $stripeError = StripeInvalidRequestException::factory($errorMessage);
        $stripeActionResult = new StripeWebhookEndpointActionResult(successful: false, stripeError: $stripeError);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(new DeleteStripeWebhookEndpointAction($stripeWebhookEndpointConfig))
            ->willReturn($stripeActionResult);

        $translatedError = 'Translated error message.';
        $this->translator
            ->expects(self::once())
            ->method('trans')
            ->with(
                'oro.stripe_payment.webhook_endpoint.delete.error.message',
                ['%message%' => $errorMessage]
            )
            ->willReturn($translatedError);

        $webhookCreateField
            ->expects(self::once())
            ->method('addError')
            ->with(new FormError($translatedError));

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to delete Stripe webhook endpoint for {settings_class} #{settings_id}: {message}',
                [
                    'settings_class' => get_class($stripePaymentElementSettings),
                    'settings_id' => $stripePaymentElementSettings->getId() ?? 'NEW',
                    'message' => $errorMessage,
                    'exception' => $stripeError,
                ]
            );

        $event = new PostSubmitEvent($form, $channel);

        $this->subscriber->onPostSubmit($event);

        self::assertNull($stripePaymentElementSettings->getWebhookStripeId());
    }

    public function testOnPostSubmitWithWebhookCreateEnabled(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        $channel->setTransport($stripePaymentElementSettings);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);
        $webhookCreateField = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('get')
            ->with('webhookCreate')
            ->willReturn($webhookCreateField);

        $webhookCreateField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(true);

        $stripeWebhookEndpointConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripeWebhookEndpointConfig);

        $stripeWebhookEndpoint = new StripeWebhookEndpoint(self::WEBHOOK_STRIPE_ID);
        $stripeWebhookEndpoint->secret = self::WEBHOOK_SECRET;

        $stripeActionResult = new StripeWebhookEndpointActionResult(
            successful: true,
            stripeWebhookEndpoint: $stripeWebhookEndpoint
        );

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(new CreateOrUpdateStripeWebhookEndpointAction($stripeWebhookEndpointConfig))
            ->willReturn($stripeActionResult);

        $event = new PostSubmitEvent($form, $channel);

        $this->subscriber->onPostSubmit($event);

        self::assertEquals(self::WEBHOOK_STRIPE_ID, $stripePaymentElementSettings->getWebhookStripeId());
        self::assertEquals(self::WEBHOOK_SECRET, $stripePaymentElementSettings->getWebhookSecret());
    }

    public function testOnPostSubmitWithWebhookCreateEnabledAndNullStripeObject(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        $stripePaymentElementSettings->setWebhookStripeId(self::WEBHOOK_STRIPE_ID);
        $stripePaymentElementSettings->setWebhookSecret(self::WEBHOOK_SECRET);

        $channel->setTransport($stripePaymentElementSettings);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);
        $webhookCreateField = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('get')
            ->with('webhookCreate')
            ->willReturn($webhookCreateField);

        $webhookCreateField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(true);

        $stripeWebhookEndpointConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripeWebhookEndpointConfig);

        $stripeActionResult = new StripeWebhookEndpointActionResult(successful: true);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(new CreateOrUpdateStripeWebhookEndpointAction($stripeWebhookEndpointConfig))
            ->willReturn($stripeActionResult);

        $event = new PostSubmitEvent($form, $channel);

        $this->subscriber->onPostSubmit($event);

        self::assertEquals(self::WEBHOOK_STRIPE_ID, $stripePaymentElementSettings->getWebhookStripeId());
        self::assertEquals(self::WEBHOOK_SECRET, $stripePaymentElementSettings->getWebhookSecret());
    }

    public function testOnPostSubmitWithWebhookCreateEnabledAndEmptySecret(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        $stripePaymentElementSettings->setWebhookStripeId(self::WEBHOOK_STRIPE_ID);
        $stripePaymentElementSettings->setWebhookSecret(self::WEBHOOK_SECRET);

        $channel->setTransport($stripePaymentElementSettings);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);
        $webhookCreateField = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('get')
            ->with('webhookCreate')
            ->willReturn($webhookCreateField);

        $webhookCreateField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(true);

        $stripeWebhookEndpointConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripeWebhookEndpointConfig);

        $stripeWebhookEndpoint = new StripeWebhookEndpoint(self::WEBHOOK_STRIPE_ID);
        $stripeWebhookEndpoint->secret = null;

        $stripeActionResult = new StripeWebhookEndpointActionResult(
            successful: true,
            stripeWebhookEndpoint: $stripeWebhookEndpoint
        );

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(new CreateOrUpdateStripeWebhookEndpointAction($stripeWebhookEndpointConfig))
            ->willReturn($stripeActionResult);

        $event = new PostSubmitEvent($form, $channel);

        $this->subscriber->onPostSubmit($event);

        self::assertEquals(self::WEBHOOK_STRIPE_ID, $stripePaymentElementSettings->getWebhookStripeId());
        self::assertEquals(self::WEBHOOK_SECRET, $stripePaymentElementSettings->getWebhookSecret());
    }

    public function testOnPostSubmitWithWebhookCreateEnabledAndNotSuccessful(): void
    {
        $channel = new Channel();
        $channel->setType(self::CHANNEL_TYPE);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings, 42);
        $stripePaymentElementSettings->setWebhookStripeId(self::WEBHOOK_STRIPE_ID);
        $stripePaymentElementSettings->setWebhookSecret(self::WEBHOOK_SECRET);

        $channel->setTransport($stripePaymentElementSettings);

        $form = $this->createMock(FormInterface::class);
        $transportForm = $this->createMock(FormInterface::class);
        $webhookCreateField = $this->createMock(FormInterface::class);

        $form
            ->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        $form
            ->expects(self::once())
            ->method('get')
            ->with('transport')
            ->willReturn($transportForm);

        $transportForm
            ->expects(self::once())
            ->method('get')
            ->with('webhookCreate')
            ->willReturn($webhookCreateField);

        $webhookCreateField
            ->expects(self::once())
            ->method('getData')
            ->willReturn(true);

        $stripeWebhookEndpointConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripeWebhookEndpointConfig);

        $exceptionMessage = 'An error occurred while creating or updating the webhook.';
        $stripeException = StripeInvalidRequestException::factory($exceptionMessage);
        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(new CreateOrUpdateStripeWebhookEndpointAction($stripeWebhookEndpointConfig))
            ->willReturn(new StripeWebhookEndpointActionResult(successful: false, stripeError: $stripeException));

        $translatedError = 'Translated error message.';
        $this->translator
            ->expects(self::once())
            ->method('trans')
            ->with(
                'oro.stripe_payment.webhook_endpoint.create_update.error.message',
                ['%message%' => $exceptionMessage]
            )
            ->willReturn($translatedError);

        $webhookCreateField
            ->expects(self::once())
            ->method('addError')
            ->with(new FormError($translatedError));

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to create/update Stripe webhook endpoint for {settings_class} #{settings_id}: {message}',
                [
                    'settings_class' => get_class($stripePaymentElementSettings),
                    'settings_id' => $stripePaymentElementSettings->getId(),
                    'message' => $stripeException->getMessage(),
                    'exception' => $stripeException,
                ]
            );

        $event = new PostSubmitEvent($form, $channel);

        $this->subscriber->onPostSubmit($event);

        self::assertEquals(self::WEBHOOK_STRIPE_ID, $stripePaymentElementSettings->getWebhookStripeId());
        self::assertEquals(self::WEBHOOK_SECRET, $stripePaymentElementSettings->getWebhookSecret());
    }
}
