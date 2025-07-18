<?php

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\Form\Type;

use Oro\Bundle\FormBundle\Form\Type\OroPlaceholderPasswordType;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Form\Type\ChannelType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\StripePaymentBundle\Configuration\StripePaymentConfiguration;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\Form\Type\StripePaymentElementSettingsType;
use Oro\Bundle\StripePaymentBundle\Test\StripeClient\MockingStripeClient;
use Oro\Bundle\TestFrameworkBundle\Test\Form\FormAwareTestTrait;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Component\Testing\ReflectionUtil;
use Stripe\WebhookEndpoint as StripeWebhookEndpoint;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class StripePaymentElementSettingsTypeTest extends WebTestCase
{
    use FormAwareTestTrait;

    private const string INTEGRATION_CHANNEL_TYPE = 'stripe_payment_element';

    private StripePaymentConfiguration $stripePaymentConfiguration;

    private LocalizationHelper $localizationHelper;

    private UrlGeneratorInterface $urlGenerator;

    private RequestStack $requestStack;

    private string $originalHost;

    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();

        $this->loadFixtures([LoadUser::class]);

        $this->stripePaymentConfiguration = self::getContainer()->get('oro_stripe_payment.configuration');
        $this->localizationHelper = self::getContainer()->get('oro_locale.helper.localization');
        $this->urlGenerator = self::getContainer()->get('router');
        $this->requestStack = self::getContainer()->get('request_stack');

        MockingStripeClient::instance()->reset();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->requestStack->pop();

        MockingStripeClient::instance()->reset();
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testFormContainsRequiredFields(): void
    {
        $this->requestStack->push(Request::create('http://127.0.0.1/admin/integration/update/7'));

        $integrationChannel = (new Channel())
            ->setType('stripe_payment_element')
            ->setTransport(new StripePaymentElementSettings());
        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);

        self::assertTrue($integrationForm->has('transport'));

        self::assertFormOptions(
            $integrationForm->get('transport'),
            ['data_class' => StripePaymentElementSettings::class,]
        );

        /** @var StripePaymentElementSettings $stripePaymentElementSettings */
        $stripePaymentElementSettings = $integrationForm->get('transport')->getData();
        self::assertInstanceOf(StripePaymentElementSettings::class, $stripePaymentElementSettings);

        self::assertFormHasField($integrationForm, 'transport', StripePaymentElementSettingsType::class);
        self::assertFormHasField($integrationForm->get('transport'), 'apiPublicKey', TextType::class, [
            'label' => 'oro.stripe_payment.payment_element.api_public_key.label',
            'required' => true,
        ]);
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'apiSecretKey',
            OroPlaceholderPasswordType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.api_secret_key.label',
                'required' => true,
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'paymentMethodName',
            TextType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.payment_method_name.label',
                'tooltip' => 'oro.stripe_payment.payment_element.payment_method_name.tooltip',
                'required' => true,
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'paymentMethodLabels',
            LocalizedFallbackValueCollectionType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.payment_method_labels.label',
                'tooltip' => 'oro.stripe_payment.payment_element.payment_method_labels.tooltip',
                'required' => true,
                'entry_options' => ['constraints' => [new NotBlank()]],
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'paymentMethodShortLabels',
            LocalizedFallbackValueCollectionType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.payment_method_short_labels.label',
                'tooltip' => 'oro.stripe_payment.payment_element.payment_method_short_labels.tooltip',
                'required' => true,
                'entry_options' => ['constraints' => [new NotBlank()]],
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'webhookCreate',
            CheckboxType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                'required' => false,
                'data' => false,
                'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'webhookAccessId',
            HiddenType::class,
            [
                'error_bubbling' => true,
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'webhookSecret',
            OroPlaceholderPasswordType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.webhook_secret.label',
                'tooltip' => 'oro.stripe_payment.payment_element.webhook_secret.tooltip',
                'required' => true,
                'block_prefix' => 'oro_stripe_payment_element_settings_webhook_secret',
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'captureMethod',
            ChoiceType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.capture_method.label',
                'tooltip' => 'oro.stripe_payment.payment_element.capture_method.tooltip',
                'choices' => [
                    'oro.stripe_payment.payment_element.capture_method.manual' => 'manual',
                    'oro.stripe_payment.payment_element.capture_method.automatic' => 'automatic',
                ],
                'required' => true,
                'block_prefix' => 'oro_stripe_payment_element_settings_capture_method',
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'reAuthorizationEnabled',
            CheckboxType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.re_authorization_enabled.label',
                'tooltip' => 'oro.stripe_payment.payment_element.re_authorization_enabled.tooltip',
                'required' => false,
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'reAuthorizationEmail',
            TextType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.re_authorization_email.label',
                'tooltip' => 'oro.stripe_payment.payment_element.re_authorization_email.tooltip',
                'required' => false,
            ]
        );
        self::assertFormHasField(
            $integrationForm->get('transport'),
            'userMonitoringEnabled',
            CheckboxType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.user_monitoring_enabled.label',
                'tooltip' => 'oro.stripe_payment.payment_element.user_monitoring_enabled.tooltip',
                'required' => false,
            ]
        );
    }

    public function testFormHasWebhookCreateTrueWhenNewSettingsAndNonLocalUrl(): void
    {
        $this->requestStack->push(Request::create('http://example.com/admin/integration/update/7'));

        $integrationChannel = (new Channel())
            ->setType('stripe_payment_element')
            ->setTransport(new StripePaymentElementSettings());
        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);

        self::assertTrue($integrationForm->has('transport'));

        self::assertFormOptions(
            $integrationForm->get('transport'),
            ['data_class' => StripePaymentElementSettings::class,]
        );

        self::assertFormHasField(
            $integrationForm->get('transport'),
            'webhookCreate',
            CheckboxType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                'required' => false,
                'data' => true,
                'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
            ]
        );
    }

    public function testFormHasWebhookCreateTrueWhenNotNewSettingsAndHasStripeId(): void
    {
        $stripePaymentElementSettings = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings, 42);
        $stripePaymentElementSettings->setWebhookStripeId('wh_123');

        $integrationChannel = (new Channel())
            ->setType('stripe_payment_element')
            ->setTransport($stripePaymentElementSettings);
        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);

        self::assertTrue($integrationForm->has('transport'));

        self::assertFormOptions(
            $integrationForm->get('transport'),
            ['data_class' => StripePaymentElementSettings::class,]
        );

        self::assertFormHasField(
            $integrationForm->get('transport'),
            'webhookCreate',
            CheckboxType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                'required' => false,
                'data' => true,
                'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
            ]
        );
    }

    public function testFormHasWebhookCreateFalseWhenNotNewSettingsAndHasNoStripeId(): void
    {
        $stripePaymentElementSettings = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings, 42);

        $integrationChannel = (new Channel())
            ->setType('stripe_payment_element')
            ->setTransport($stripePaymentElementSettings);
        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);

        self::assertTrue($integrationForm->has('transport'));

        self::assertFormOptions(
            $integrationForm->get('transport'),
            ['data_class' => StripePaymentElementSettings::class,]
        );

        self::assertFormHasField(
            $integrationForm->get('transport'),
            'webhookCreate',
            CheckboxType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                'required' => false,
                'data' => false,
                'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
            ]
        );
    }

    public function testFormViewContainsCustomBlockPrefixes(): void
    {
        $integrationChannel = new Channel();
        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);
        $integrationForm->submit(
            [
                'name' => self::INTEGRATION_CHANNEL_TYPE,
                'type' => self::INTEGRATION_CHANNEL_TYPE,
                'transportType' => self::INTEGRATION_CHANNEL_TYPE,
            ]
        );

        $integrationFormView = $integrationForm->createView();
        self::assertArrayHasKey('transport', $integrationFormView->children);

        $transportFormView = $integrationFormView->children['transport'];

        self::assertContains('oro_stripe_payment_element_settings', $transportFormView->vars['block_prefixes']);

        self::assertArrayHasKey('webhookSecret', $transportFormView->children);
        $webhookSecretFormView = $transportFormView->children['webhookSecret'];
        self::assertContains(
            'oro_stripe_payment_element_settings_webhook_secret',
            $webhookSecretFormView->vars['block_prefixes']
        );
    }

    public function testFormContainsManualCaptureMethodsViewVar(): void
    {
        $integrationChannel = new Channel();
        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);
        $integrationForm->submit(
            [
                'name' => self::INTEGRATION_CHANNEL_TYPE,
                'type' => self::INTEGRATION_CHANNEL_TYPE,
                'transportType' => self::INTEGRATION_CHANNEL_TYPE,
            ]
        );

        $integrationFormView = $integrationForm->createView();
        self::assertArrayHasKey('transport', $integrationFormView->children);

        $transportFormView = $integrationFormView->children['transport'];

        self::assertArrayHasKey('captureMethod', $transportFormView->children);
        self::assertArrayHasKey(
            'payment_method_types_with_manual_capture',
            $transportFormView->children['captureMethod']->vars
        );
        self::assertEquals(
            $this->stripePaymentConfiguration->getPaymentMethodTypesWithManualCapture(),
            $transportFormView->children['captureMethod']->vars['payment_method_types_with_manual_capture']
        );
    }

    public function testFormContainsWebhookEndpointUrlViewVar(): void
    {
        $integrationChannel = new Channel();
        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);
        $integrationForm->submit(
            [
                'name' => self::INTEGRATION_CHANNEL_TYPE,
                'type' => self::INTEGRATION_CHANNEL_TYPE,
                'transportType' => self::INTEGRATION_CHANNEL_TYPE,
            ]
        );

        $integrationFormView = $integrationForm->createView();
        self::assertArrayHasKey('transport', $integrationFormView->children);

        $transportFormView = $integrationFormView->children['transport'];

        self::assertArrayHasKey('webhookCreate', $transportFormView->children);

        self::assertArrayHasKey('webhookSecret', $transportFormView->children);
        self::assertArrayHasKey(
            'webhook_endpoint_url',
            $transportFormView->children['webhookSecret']->vars
        );
        /** @var StripePaymentElementSettings $stripePaymentElementSettings */
        $stripePaymentElementSettings = $integrationChannel->getTransport();
        self::assertEquals(
            $this->urlGenerator->generate(
                'oro_stripe_payment_webhook_payment_element',
                ['webhookAccessId' => $stripePaymentElementSettings->getWebhookAccessId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            $transportFormView->children['webhookSecret']->vars['webhook_endpoint_url']
        );
    }

    public function testFormContainsDefaultLabels(): void
    {
        $integrationChannel = new Channel();
        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);
        $integrationForm->submit(
            [
                'name' => self::INTEGRATION_CHANNEL_TYPE,
                'type' => self::INTEGRATION_CHANNEL_TYPE,
                'transportType' => self::INTEGRATION_CHANNEL_TYPE,
            ]
        );

        /** @var StripePaymentElementSettings $stripePaymentElementSettings */
        $stripePaymentElementSettings = $integrationForm->get('transport')->getData();
        self::assertInstanceOf(StripePaymentElementSettings::class, $stripePaymentElementSettings);
    }

    public function testFormSubmitWhenWebhookCreateFalse(): void
    {
        $integrationChannel = new Channel();
        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);
        $integrationForm->submit(
            [
                'name' => self::INTEGRATION_CHANNEL_TYPE,
                'type' => self::INTEGRATION_CHANNEL_TYPE,
                'transportType' => self::INTEGRATION_CHANNEL_TYPE,
            ]
        );

        /** @var StripePaymentElementSettings $stripePaymentElementSettings */
        $stripePaymentElementSettings = $integrationForm->get('transport')->getData();
        self::assertInstanceOf(StripePaymentElementSettings::class, $stripePaymentElementSettings);

        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);
        $paymentMethodName = 'Stripe Payment Element';
        $paymentMethodLabel = 'Stripe';
        $apiPublicKey = 'pk_test_123';
        $apiSecretKey = 'sk_test_123';
        $webhookSecret = 'ws_123';
        $captureMethod = 'automatic';
        $reAuthorizationEnabled = false;
        $reAuthorizationEmail = null;
        $userMonitoringEnabled = false;

        $integrationForm->submit(
            [
                'name' => self::INTEGRATION_CHANNEL_TYPE,
                'type' => self::INTEGRATION_CHANNEL_TYPE,
                'transportType' => self::INTEGRATION_CHANNEL_TYPE,
                'transport' => [
                    'apiPublicKey' => $apiPublicKey,
                    'apiSecretKey' => $apiSecretKey,
                    'paymentMethodName' => $paymentMethodName,
                    'paymentMethodLabels' => ['values' => ['default' => $paymentMethodLabel]],
                    'paymentMethodShortLabels' => ['values' => ['default' => $paymentMethodLabel]],
                    'webhookCreate' => false,
                    'webhookAccessId' => $stripePaymentElementSettings->getWebhookAccessId(),
                    'webhookStripeId' => null,
                    'webhookSecret' => $webhookSecret,
                    'captureMethod' => $captureMethod,
                    'reAuthorizationEnabled' => $reAuthorizationEnabled,
                    'reAuthorizationEmail' => $reAuthorizationEmail,
                    'userMonitoringEnabled' => $userMonitoringEnabled,
                ],
            ]
        );

        self::assertEquals($paymentMethodName, $stripePaymentElementSettings->getPaymentMethodName());
        self::assertEquals(
            $paymentMethodLabel,
            $this->localizationHelper->getLocalizedValue($stripePaymentElementSettings->getPaymentMethodLabels())
        );
        self::assertEquals(
            $paymentMethodLabel,
            $this->localizationHelper->getLocalizedValue($stripePaymentElementSettings->getPaymentMethodShortLabels())
        );
        self::assertEquals($apiPublicKey, $stripePaymentElementSettings->getApiPublicKey());
        self::assertEquals($apiSecretKey, $stripePaymentElementSettings->getApiSecretKey());
        self::assertEquals($webhookSecret, $stripePaymentElementSettings->getWebhookSecret());
        self::assertEquals($captureMethod, $stripePaymentElementSettings->getCaptureMethod());
        self::assertEquals($reAuthorizationEnabled, $stripePaymentElementSettings->isReAuthorizationEnabled());
        self::assertEquals($reAuthorizationEmail, $stripePaymentElementSettings->getReAuthorizationEmail());
        self::assertEquals($userMonitoringEnabled, $stripePaymentElementSettings->isUserMonitoringEnabled());
    }

    public function testFormSubmitCreatesWebhookEndpointWhenWebhookCreateTrue(): void
    {
        /** @var User $user */
        $user = $this->getReference(LoadUser::USER);

        $stripePaymentElementSettings = new StripePaymentElementSettings();
        $integrationChannel = (new Channel())
            ->setName('Stripe Payment Element')
            ->setType(self::INTEGRATION_CHANNEL_TYPE)
            ->setTransport($stripePaymentElementSettings)
            ->setOrganization($user->getOrganization());

        $paymentMethodName = 'Stripe Payment Element';
        $paymentMethodLabel = 'Stripe';
        $apiPublicKey = 'pk_test_123';
        $apiSecretKey = 'sk_test_123';
        $captureMethod = 'manual';
        $reAuthorizationEnabled = true;
        $reAuthorizationEmail = 'email@example.org';
        $userMonitoringEnabled = true;

        $stripeWebhookEndpoint = new StripeWebhookEndpoint('we_123');
        $stripeWebhookEndpoint->secret = 'ws_123';

        MockingStripeClient::addMockResponse($stripeWebhookEndpoint);

        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);
        $integrationForm->submit(
            [
                'name' => self::INTEGRATION_CHANNEL_TYPE,
                'type' => self::INTEGRATION_CHANNEL_TYPE,
                'transportType' => self::INTEGRATION_CHANNEL_TYPE,
                'transport' => [
                    'apiPublicKey' => $apiPublicKey,
                    'apiSecretKey' => $apiSecretKey,
                    'paymentMethodName' => $paymentMethodName,
                    'paymentMethodLabels' => ['values' => ['default' => $paymentMethodLabel]],
                    'paymentMethodShortLabels' => ['values' => ['default' => $paymentMethodLabel]],
                    'webhookCreate' => true,
                    'webhookAccessId' => $stripePaymentElementSettings->getWebhookAccessId(),
                    'webhookSecret' => '',
                    'captureMethod' => $captureMethod,
                    'reAuthorizationEnabled' => $reAuthorizationEnabled,
                    'reAuthorizationEmail' => $reAuthorizationEmail,
                    'userMonitoringEnabled' => $userMonitoringEnabled,
                ],
            ],
            false
        );

        self::assertEquals($paymentMethodName, $stripePaymentElementSettings->getPaymentMethodName());
        self::assertEquals(
            $paymentMethodLabel,
            $this->localizationHelper->getLocalizedValue($stripePaymentElementSettings->getPaymentMethodLabels())
        );
        self::assertEquals(
            $paymentMethodLabel,
            $this->localizationHelper->getLocalizedValue($stripePaymentElementSettings->getPaymentMethodShortLabels())
        );
        self::assertEquals($apiPublicKey, $stripePaymentElementSettings->getApiPublicKey());
        self::assertEquals($apiSecretKey, $stripePaymentElementSettings->getApiSecretKey());
        self::assertEquals($stripeWebhookEndpoint->id, $stripePaymentElementSettings->getWebhookStripeId());
        self::assertEquals($stripeWebhookEndpoint->secret, $stripePaymentElementSettings->getWebhookSecret());
        self::assertEquals($captureMethod, $stripePaymentElementSettings->getCaptureMethod());
        self::assertEquals($reAuthorizationEnabled, $stripePaymentElementSettings->isReAuthorizationEnabled());
        self::assertEquals($reAuthorizationEmail, $stripePaymentElementSettings->getReAuthorizationEmail());
        self::assertEquals($userMonitoringEnabled, $stripePaymentElementSettings->isUserMonitoringEnabled());

        $apiRequestLog = current(MockingStripeClient::instance()->getRequestLogs());
        self::assertEquals('post', $apiRequestLog['method']);
        self::assertEquals('/v1/webhook_endpoints', $apiRequestLog['path']);
        self::assertEquals([
            'url' => $this->urlGenerator->generate(
                'oro_stripe_payment_webhook_payment_element',
                ['webhookAccessId' => $stripePaymentElementSettings->getWebhookAccessId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'enabled_events' => [
                'payment_intent.succeeded',
                'payment_intent.payment_failed',
                'payment_intent.canceled',
                'refund.updated',
            ],
            'description' => 'OroCommerce Webhook Stripe Payment Element',
            'metadata' => [
                'payment_method_name' => $paymentMethodName,
            ],
        ], $apiRequestLog['params']);

        $apiResponseLog = current(MockingStripeClient::instance()->getResponseLogs());
        self::assertEquals($stripeWebhookEndpoint->toArray(), $apiResponseLog['response']);
    }

    public function testFormSubmitDeletesWebhookEndpointWhenWebhookCreateBecomesFalse(): void
    {
        /** @var User $user */
        $user = $this->getReference(LoadUser::USER);

        $webhookStripeId = 'we_123';
        $stripePaymentElementSettings = (new StripePaymentElementSettings())
            ->setWebhookStripeId($webhookStripeId);

        $integrationChannel = (new Channel())
            ->setName('Stripe Payment Element')
            ->setType(self::INTEGRATION_CHANNEL_TYPE)
            ->setTransport($stripePaymentElementSettings)
            ->setOrganization($user->getOrganization());

        $stripeWebhookEndpoint = new StripeWebhookEndpoint($webhookStripeId);
        $stripeWebhookEndpoint->secret = 'ws_123';

        MockingStripeClient::addMockResponse($stripeWebhookEndpoint);

        $integrationForm = self::createForm(ChannelType::class, $integrationChannel);
        $paymentMethodName = 'Stripe Payment Element';
        $paymentMethodLabel = 'Stripe';
        $apiPublicKey = 'pk_test_123';
        $apiSecretKey = 'sk_test_123';
        $captureMethod = 'manual';
        $webhookSecret = 'ws_123_custom';
        $reAuthorizationEnabled = true;
        $reAuthorizationEmail = 'email@example.org';
        $userMonitoringEnabled = true;

        $integrationForm->submit(
            [
                'name' => self::INTEGRATION_CHANNEL_TYPE,
                'type' => self::INTEGRATION_CHANNEL_TYPE,
                'transportType' => self::INTEGRATION_CHANNEL_TYPE,
                'transport' => [
                    'apiPublicKey' => $apiPublicKey,
                    'apiSecretKey' => $apiSecretKey,
                    'paymentMethodName' => $paymentMethodName,
                    'paymentMethodLabels' => ['values' => ['default' => $paymentMethodLabel]],
                    'paymentMethodShortLabels' => ['values' => ['default' => $paymentMethodLabel]],
                    'webhookCreate' => false,
                    'webhookAccessId' => $stripePaymentElementSettings->getWebhookAccessId(),
                    'webhookSecret' => $webhookSecret,
                    'captureMethod' => $captureMethod,
                    'reAuthorizationEnabled' => $reAuthorizationEnabled,
                    'reAuthorizationEmail' => $reAuthorizationEmail,
                    'userMonitoringEnabled' => $userMonitoringEnabled,
                ],
            ],
            false
        );

        self::assertEquals($paymentMethodName, $stripePaymentElementSettings->getPaymentMethodName());
        self::assertEquals(
            $paymentMethodLabel,
            $this->localizationHelper->getLocalizedValue($stripePaymentElementSettings->getPaymentMethodLabels())
        );
        self::assertEquals(
            $paymentMethodLabel,
            $this->localizationHelper->getLocalizedValue($stripePaymentElementSettings->getPaymentMethodShortLabels())
        );
        self::assertEquals($apiPublicKey, $stripePaymentElementSettings->getApiPublicKey());
        self::assertEquals($apiSecretKey, $stripePaymentElementSettings->getApiSecretKey());
        self::assertNull($stripePaymentElementSettings->getWebhookStripeId());
        self::assertEquals($webhookSecret, $stripePaymentElementSettings->getWebhookSecret());
        self::assertEquals($captureMethod, $stripePaymentElementSettings->getCaptureMethod());
        self::assertEquals($reAuthorizationEnabled, $stripePaymentElementSettings->isReAuthorizationEnabled());
        self::assertEquals($reAuthorizationEmail, $stripePaymentElementSettings->getReAuthorizationEmail());
        self::assertEquals($userMonitoringEnabled, $stripePaymentElementSettings->isUserMonitoringEnabled());

        $apiRequestLog = current(MockingStripeClient::instance()->getRequestLogs());
        self::assertEquals('delete', $apiRequestLog['method']);
        self::assertEquals('/v1/webhook_endpoints/' . $webhookStripeId, $apiRequestLog['path']);
        self::assertNull($apiRequestLog['params']);

        $apiResponseLog = current(MockingStripeClient::instance()->getResponseLogs());
        self::assertEquals($stripeWebhookEndpoint->toArray(), $apiResponseLog['response']);
    }
}
