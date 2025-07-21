<?php

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;

class LoadStripePaymentElementChannelData extends AbstractFixture implements DependentFixtureInterface
{
    public const string STRIPE_PAYMENT_ELEMENT_CHANNEL_1 = 'stripe_payment_element_channel_1';
    public const string STRIPE_PAYMENT_ELEMENT_CHANNEL_2 = 'stripe_payment_element_channel_2';
    public const string STRIPE_PAYMENT_ELEMENT_CHANNEL_3 = 'stripe_payment_element_channel_3';

    private const array CHANNEL_DATA = [
        self::STRIPE_PAYMENT_ELEMENT_CHANNEL_1 => [
            'name' => 'Stripe Payment Element 1',
            'type' => 'stripe_payment_element',
            'enabled' => true,
            'transport' => LoadStripePaymentElementSettingsData::STRIPE_PAYMENT_ELEMENT_SETTINGS_1,
        ],
        self::STRIPE_PAYMENT_ELEMENT_CHANNEL_2 => [
            'name' => 'Stripe Payment Element 2',
            'type' => 'stripe_payment_element',
            'enabled' => true,
            'transport' => LoadStripePaymentElementSettingsData::STRIPE_PAYMENT_ELEMENT_SETTINGS_2,
        ],
        self::STRIPE_PAYMENT_ELEMENT_CHANNEL_3 => [
            'name' => 'Stripe Payment Element 3',
            'type' => 'stripe_payment_element',
            'enabled' => false,
            'transport' => LoadStripePaymentElementSettingsData::STRIPE_PAYMENT_ELEMENT_SETTINGS_3,
        ],
    ];

    #[\Override]
    public function getDependencies(): array
    {
        return [LoadStripePaymentElementSettingsData::class, LoadOrganization::class, LoadUser::class];
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        foreach (self::CHANNEL_DATA as $reference => $data) {
            $entity = new Channel();
            $entity->setName($data['name']);
            $entity->setType($data['type']);
            $entity->setEnabled($data['enabled']);
            $entity->setDefaultUserOwner($this->getReference(LoadUser::USER));
            $entity->setOrganization($this->getReference(LoadOrganization::ORGANIZATION));
            $entity->setTransport($this->getReference($data['transport']));

            $this->setReference($reference, $entity);

            $manager->persist($entity);
        }
        $manager->flush();
    }
}
