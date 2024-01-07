<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;

class LoadStripeChannelData extends AbstractFixture implements DependentFixtureInterface
{
    private array $channelData = [
        'stripe:channel_1' => [
            'name' => 'Stripe',
            'type' => 'stripe',
            'enabled' => true,
            'userMonitoring' => true,
        ],
        'stripe:channel_2' => [
            'name' => 'Stripe2',
            'type' => 'stripe',
            'enabled' => true,
            'userMonitoring' => false,
        ],
        'stripe:channel_3' => [
            'name' => 'Stripe3',
            'type' => 'stripe',
            'enabled' => false,
            'userMonitoring' => true,
        ],
    ];

    /**
     * {@inheritDoc}
     */
    public function getDependencies(): array
    {
        return [LoadOrganization::class, LoadUser::class];
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager): void
    {
        foreach ($this->channelData as $reference => $data) {
            $entity = new Channel();
            $entity->setName($data['name']);
            $entity->setType($data['type']);
            $entity->setEnabled($data['enabled']);
            $entity->setDefaultUserOwner($this->getReference(LoadUser::USER));
            $entity->setOrganization($this->getReference(LoadOrganization::ORGANIZATION));
            $entity->setTransport((new StripeTransportSettings())->setUserMonitoring($data['userMonitoring']));
            $this->setReference($reference, $entity);
            $manager->persist($entity);
        }
        $manager->flush();
    }
}
