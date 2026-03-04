<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\PaymentBundle\Entity\PaymentMethodConfig;
use Oro\Bundle\PaymentBundle\Entity\PaymentMethodsConfigsRule;
use Oro\Bundle\PaymentBundle\Tests\Functional\Entity\DataFixtures\LoadPaymentMethodsConfigsRuleData as BaseFixture;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\Integration\StripePaymentElement\StripePaymentElementIntegrationChannelType;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;

class LoadStripePaymentElementMethodData extends BaseFixture implements DependentFixtureInterface
{
    public const string STRIPE_PAYMENT_ELEMENT_CHANNEL = 'stripe_payment_element_channel';
    public const string STRIPE_PAYMENT_ELEMENT_METHOD_CONFIG = 'stripe_payment_element_method_config';

    #[\Override]
    public function getDependencies(): array
    {
        return [
            LoadOrganization::class,
            LoadUser::class,
        ];
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        parent::load($manager);

        $channel = $this->createStripePaymentElementChannel($manager);

        $paymentMethodIdentifier = StripePaymentElementIntegrationChannelType::TYPE . '_' . $channel->getId();

        $paymentMethodConfig = new PaymentMethodConfig();
        $paymentMethodConfig->setType($paymentMethodIdentifier);
        $this->setReference(self::STRIPE_PAYMENT_ELEMENT_METHOD_CONFIG, $paymentMethodConfig);

        /** @var PaymentMethodsConfigsRule $rule */
        $rule = $this->getReference('payment.payment_methods_configs_rule.1');
        $rule->addMethodConfig($paymentMethodConfig);

        $manager->persist($paymentMethodConfig);
        $manager->flush();
    }

    private function createStripePaymentElementChannel(ObjectManager $manager): Channel
    {
        $label = new LocalizedFallbackValue();
        $label->setString('Stripe Payment Element');

        $shortLabel = new LocalizedFallbackValue();
        $shortLabel->setString('Stripe');

        $transportSettings = new StripePaymentElementSettings();
        $transportSettings->setApiPublicKey('pk_test_123');
        $transportSettings->setApiSecretKey('sk_test_123');
        $transportSettings->setWebhookSecret('whsec_123');
        $transportSettings->setCaptureMethod('automatic');
        $transportSettings->setUserMonitoringEnabled(false);
        $transportSettings->setReAuthorizationEnabled(false);
        $transportSettings->setPaymentMethodName('Stripe Payment Element');
        $transportSettings->addPaymentMethodLabel($label);
        $transportSettings->addPaymentMethodShortLabel($shortLabel);
        $manager->persist($transportSettings);

        $channel = new Channel();
        $channel->setName('Stripe Payment Element');
        $channel->setType(StripePaymentElementIntegrationChannelType::TYPE);
        $channel->setEnabled(true);
        $channel->setDefaultUserOwner($this->getReference(LoadUser::USER));
        $channel->setOrganization($this->getReference(LoadOrganization::ORGANIZATION));
        $channel->setTransport($transportSettings);
        $manager->persist($channel);
        $manager->flush();

        $this->setReference(self::STRIPE_PAYMENT_ELEMENT_CHANNEL, $channel);

        return $channel;
    }
}
