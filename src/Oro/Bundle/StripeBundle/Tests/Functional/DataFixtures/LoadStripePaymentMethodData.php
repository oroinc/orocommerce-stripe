<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\PaymentBundle\Entity\PaymentMethodConfig;
use Oro\Bundle\PaymentBundle\Entity\PaymentMethodsConfigsRule;
use Oro\Bundle\PaymentBundle\Tests\Functional\Entity\DataFixtures\LoadPaymentMethodsConfigsRuleData as BaseFixture;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;

class LoadStripePaymentMethodData extends BaseFixture
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        parent::load($manager);

        $channel = $this->createStripeChannel($manager);

        $methodConfig = new PaymentMethodConfig();
        $identifier = $this->container
            ->get('oro_stripe.card_method.generator.identifier')
            ->generateIdentifier($channel);
        $methodConfig->setType($identifier);

        /** @var PaymentMethodsConfigsRule $methodsConfigsRule */
        $methodsConfigsRule = $this->getReference('payment.payment_methods_configs_rule.1');
        $methodsConfigsRule->addMethodConfig($methodConfig);

        $manager->flush();
    }

    private function createStripeChannel(ObjectManager $manager): Channel
    {
        $transportSettings = new StripeTransportSettings();
        $transportSettings->setPaymentAction('automatic');
        $transportSettings->setUserMonitoring(false);
        $transportSettings->setApiSecretKey('secret_key');
        $transportSettings->setApiPublicKey('public_key');
        $transportSettings->setSigningSecret('signing_secret');
        $manager->persist($transportSettings);

        $channel = new Channel();
        $channel->setName('Stripe');
        $channel->setType('stripe');
        $channel->setEnabled(true);
        $channel->setDefaultUserOwner($this->getReference(LoadUser::USER));
        $channel->setOrganization($this->getReference(LoadOrganization::ORGANIZATION));
        $channel->setTransport($transportSettings);
        $this->setReference('stripe_integration_channel', $channel);
        $manager->persist($channel);

        $manager->flush();

        return $channel;
    }
}
