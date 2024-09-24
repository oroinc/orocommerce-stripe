<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\Entity\Repository;

use Oro\Bundle\StripeBundle\Entity\Repository\StripeTransportSettingsRepository;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Tests\Functional\DataFixtures\LoadStripeChannelData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class StripeTransportSettingsRepositoryTest extends WebTestCase
{
    protected StripeTransportSettingsRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $this->initClient([], $this->generateBasicAuthHeader());
        $this->loadFixtures([
            LoadStripeChannelData::class,
        ]);

        $this->repository = $this->getContainer()->get('doctrine')
            ->getRepository(StripeTransportSettings::class);
    }

    public function testGetEnabledSettingsByType(): void
    {
        $this->assertCount(2, $this->repository->getEnabledSettingsByType('stripe'));
    }

    public function testGetEnabledMonitoringSettingsByType(): void
    {
        $this->assertCount(1, $this->repository->getEnabledMonitoringSettingsByType('stripe'));
    }
}
