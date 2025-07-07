<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\Entity\Repository;

use Oro\Bundle\StripePaymentBundle\Entity\Repository\StripePaymentElementSettingsRepository;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures\LoadStripePaymentElementChannelData;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures\LoadStripePaymentElementSettingsData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

final class StripePaymentElementSettingsRepositoryTest extends WebTestCase
{
    private StripePaymentElementSettingsRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();

        $this->repository = self::getContainer()->get('doctrine')->getRepository(StripePaymentElementSettings::class);
    }

    public function testFindEnabledSettingsWhenNoSettings(): void
    {
        $enabledSettings = $this->repository->findEnabledSettings();

        self::assertCount(0, $enabledSettings);
    }

    public function testFindEnabledSettings(): void
    {
        $this->loadFixtures([
            LoadStripePaymentElementChannelData::class,
        ]);

        $enabledSettings = $this->repository->findEnabledSettings();

        self::assertCount(2, $enabledSettings);
        self::assertContains(
            $this->getReference(LoadStripePaymentElementSettingsData::STRIPE_PAYMENT_ELEMENT_SETTINGS_1),
            $enabledSettings
        );
        self::assertContains(
            $this->getReference(LoadStripePaymentElementSettingsData::STRIPE_PAYMENT_ELEMENT_SETTINGS_2),
            $enabledSettings
        );
    }
}
