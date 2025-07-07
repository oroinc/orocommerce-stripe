<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\PaymentMethod\StripePaymentElement\Config;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Generator\IntegrationIdentifierGeneratorInterface;
use Oro\Bundle\LocaleBundle\DependencyInjection\Configuration;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripePaymentBundle\Configuration\StripePaymentConfiguration;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigFactory;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class StripePaymentElementConfigFactoryTest extends TestCase
{
    private const string STRIPE_API_VERSION = '2025-02-24.acacia';
    private const string STRIPE_JS_VERSION = 'basil';
    private const string WEBHOOK_ROUTE = 'oro_stripe_payment_webhook_payment_element';
    private const string EMAIL_TEMPLATE = 'stripe_payment_element_re_authorization_failure';

    private StripePaymentElementConfigFactory $factory;

    private MockObject&IntegrationIdentifierGeneratorInterface $identifierGenerator;

    private MockObject&LocalizationHelper $localizationHelper;

    private MockObject&UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        $this->identifierGenerator = $this->createMock(IntegrationIdentifierGeneratorInterface::class);
        $this->localizationHelper = $this->createMock(LocalizationHelper::class);
        $stripePaymentConfiguration = new StripePaymentConfiguration(
            [
                'payment_method_types' => [
                    'card' => ['manual_capture' => true],
                    'amazon_pay' => ['manual_capture' => true],
                ],
            ]
        );
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->factory = new StripePaymentElementConfigFactory(
            $this->identifierGenerator,
            $this->localizationHelper,
            $stripePaymentConfiguration,
            $this->urlGenerator,
            self::STRIPE_API_VERSION,
            self::STRIPE_JS_VERSION,
            self::WEBHOOK_ROUTE,
            self::EMAIL_TEMPLATE
        );
    }

    public function testCreateConfigWithAllSettings(): void
    {
        $stripePaymentElementSettings = $this->createStripePaymentElementSettings();
        $localization = $this->createMock(Localization::class);

        $paymentMethodIdentifier = 'stripe_payment_element_1';
        $this->identifierGenerator
            ->expects(self::once())
            ->method('generateIdentifier')
            ->with($stripePaymentElementSettings->getChannel())
            ->willReturn($paymentMethodIdentifier);

        $this->localizationHelper
            ->expects(self::exactly(2))
            ->method('getLocalizedValue')
            ->willReturnMap([
                [$stripePaymentElementSettings->getPaymentMethodLabels(), null, 'Stripe Payment Element'],
                [$stripePaymentElementSettings->getPaymentMethodShortLabels(), null, 'Stripe'],
            ]);

        $this->localizationHelper
            ->expects(self::once())
            ->method('getCurrentLocalization')
            ->willReturn($localization);

        $localization
            ->expects(self::once())
            ->method('getLanguageCode')
            ->willReturn('en_US');

        $webhookUrl = 'http://example.com/webhook';
        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with(
                self::WEBHOOK_ROUTE,
                ['webhookAccessId' => $stripePaymentElementSettings->getWebhookAccessId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn($webhookUrl);

        $config = $this->factory->createConfig($stripePaymentElementSettings);

        self::assertEquals([
            AbstractParameterBagPaymentConfig::FIELD_ADMIN_LABEL =>
                $stripePaymentElementSettings->getPaymentMethodName(),
            AbstractParameterBagPaymentConfig::FIELD_LABEL => 'Stripe Payment Element',
            AbstractParameterBagPaymentConfig::FIELD_SHORT_LABEL => 'Stripe',
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => $paymentMethodIdentifier,
            StripePaymentElementConfig::API_VERSION => self::STRIPE_API_VERSION,
            StripePaymentElementConfig::API_PUBLIC_KEY => $stripePaymentElementSettings->getApiPublicKey(),
            StripePaymentElementConfig::API_SECRET_KEY => $stripePaymentElementSettings->getApiSecretKey(),
            StripePaymentElementConfig::SCRIPT_VERSION => self::STRIPE_JS_VERSION,
            StripePaymentElementConfig::WEBHOOK_URL => $webhookUrl,
            StripePaymentElementConfig::WEBHOOK_ACCESS_ID => $stripePaymentElementSettings->getWebhookAccessId(),
            StripePaymentElementConfig::WEBHOOK_STRIPE_ID => $stripePaymentElementSettings->getWebhookStripeId(),
            StripePaymentElementConfig::WEBHOOK_SECRET => $stripePaymentElementSettings->getWebhookSecret(),
            StripePaymentElementConfig::CAPTURE_METHOD => $stripePaymentElementSettings->getCaptureMethod(),
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => ['card', 'amazon_pay'],
            StripePaymentElementConfig::RE_AUTHORIZATION_ENABLED =>
                $stripePaymentElementSettings->isReAuthorizationEnabled(),
            StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL => ['admin@example.com', 'support@example.com'],
            StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL_TEMPLATE => self::EMAIL_TEMPLATE,
            StripePaymentElementConfig::USER_MONITORING_ENABLED =>
                $stripePaymentElementSettings->isUserMonitoringEnabled(),
            StripePaymentElementConfig::LOCALE => 'en',
        ], $config->all());
    }

    public function testCreateConfigWithNullChannel(): void
    {
        $settings = $this->createStripePaymentElementSettings();
        ReflectionUtil::setPropertyValue($settings, 'channel', null);

        $config = $this->factory->createConfig($settings);

        self::assertNull($config->getPaymentMethodIdentifier());
    }

    public function testCreateConfigWithEmptyLocalization(): void
    {
        $settings = $this->createStripePaymentElementSettings();

        $this->localizationHelper
            ->expects(self::once())
            ->method('getCurrentLocalization')
            ->willReturn(null);

        $config = $this->factory->createConfig($settings);

        self::assertEquals(Configuration::DEFAULT_LOCALE, $config->getLocale());
    }

    public function testCreateConfigWithSimpleLanguageCode(): void
    {
        $settings = $this->createStripePaymentElementSettings();
        $localization = $this->createMock(Localization::class);

        $localization
            ->expects(self::once())
            ->method('getLanguageCode')
            ->willReturn('fr');

        $this->localizationHelper
            ->expects(self::once())
            ->method('getCurrentLocalization')
            ->willReturn($localization);

        $config = $this->factory->createConfig($settings);

        self::assertSame('fr', $config->getLocale());
    }

    public function testCreateConfigWithEmptyReAuthorizationEmail(): void
    {
        $settings = $this->createStripePaymentElementSettings();
        ReflectionUtil::setPropertyValue($settings, 'reAuthorizationEmail', '');

        $config = $this->factory->createConfig($settings);

        self::assertSame([], $config->getReAuthorizationEmail());
    }

    public function testCreateConfigWithSingleReAuthorizationEmail(): void
    {
        $settings = $this->createStripePaymentElementSettings();
        ReflectionUtil::setPropertyValue($settings, 'reAuthorizationEmail', 'admin@example.com');

        $config = $this->factory->createConfig($settings);

        self::assertSame(['admin@example.com'], $config->getReAuthorizationEmail());
    }

    public function testCreateConfigWithMultipleReAuthorizationEmails(): void
    {
        $settings = $this->createStripePaymentElementSettings();
        ReflectionUtil::setPropertyValue(
            $settings,
            'reAuthorizationEmail',
            'admin@example.com, support@example.com,finance@example.com'
        );

        $config = $this->factory->createConfig($settings);

        self::assertSame(
            ['admin@example.com', 'support@example.com', 'finance@example.com'],
            $config->getReAuthorizationEmail()
        );
    }

    public function testCreateConfigWithEmptyPaymentMethodLabels(): void
    {
        $settings = $this->createStripePaymentElementSettings();
        $settings->getPaymentMethodLabels()->clear();
        $settings->getPaymentMethodShortLabels()->clear();

        $this->localizationHelper
            ->expects(self::exactly(2))
            ->method('getLocalizedValue')
            ->willReturnMap([
                [$settings->getPaymentMethodLabels(), null, ''],
                [$settings->getPaymentMethodShortLabels(), null, ''],
            ]);

        $config = $this->factory->createConfig($settings);

        self::assertSame('', $config->getLabel());
        self::assertSame('', $config->getShortLabel());
    }

    private function createStripePaymentElementSettings(): StripePaymentElementSettings
    {
        $channel = $this->createMock(Channel::class);

        return (new StripePaymentElementSettings())
            ->setPaymentMethodName('Stripe Payment Element')
            ->addPaymentMethodLabel((new LocalizedFallbackValue())->setString('Stripe Payment'))
            ->addPaymentMethodShortLabel((new LocalizedFallbackValue())->setString('Stripe'))
            ->setApiPublicKey('pk_test_123')
            ->setApiSecretKey('sk_test_123')
            ->setWebhookAccessId('wh_test123')
            ->setWebhookStripeId('we_test123')
            ->setWebhookSecret('whsec_test123')
            ->setCaptureMethod('automatic')
            ->setReAuthorizationEnabled(true)
            ->setReAuthorizationEmail('admin@example.com, support@example.com')
            ->setUserMonitoringEnabled(true)
            ->setChannel($channel);
    }
}
