<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\UserBundle\Migrations\Data\ORM\LoadAdminUserData;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadStripeChannelData extends AbstractFixture implements ContainerAwareInterface
{
    protected array $channelData = [
        [
            'name' => 'Stripe',
            'type' => 'stripe',
            'enabled' => true,
            'reference' => 'stripe:channel_1',
            'userMonitoring' => true,
        ],
        [
            'name' => 'Stripe2',
            'type' => 'stripe',
            'enabled' => true,
            'reference' => 'stripe:channel_2',
            'userMonitoring' => false,
        ],
        [
            'name' => 'Stripe3',
            'type' => 'stripe',
            'enabled' => false,
            'reference' => 'stripe:channel_3',
            'userMonitoring' => true,
        ],
    ];

    protected ?ContainerInterface $container = null;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function load(ObjectManager $manager): void
    {
        $userManager = $this->container->get('oro_user.manager');
        $admin = $userManager->findUserByEmail(LoadAdminUserData::DEFAULT_ADMIN_EMAIL);
        $organization = $manager->getRepository(Organization::class)->getFirst();

        foreach ($this->channelData as $data) {
            $entity = new Channel();
            $entity->setName($data['name']);
            $entity->setType($data['type']);
            $entity->setEnabled($data['enabled']);
            $entity->setDefaultUserOwner($admin);
            $entity->setOrganization($organization);
            $entity->setTransport((new StripeTransportSettings())->setUserMonitoring($data['userMonitoring']));
            $this->setReference($data['reference'], $entity);

            $manager->persist($entity);
        }
        $manager->flush();
    }
}
