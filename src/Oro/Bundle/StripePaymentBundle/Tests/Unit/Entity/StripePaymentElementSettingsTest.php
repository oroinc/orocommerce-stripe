<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Component\Testing\Unit\EntityTestCaseTrait;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

final class StripePaymentElementSettingsTest extends TestCase
{
    use EntityTestCaseTrait;
    use EntityTrait;

    public function testAccessors(): void
    {
        self::assertPropertyAccessors(
            new StripePaymentElementSettings(),
            [
                ['apiPublicKey', 'sample public key'],
                ['apiSecretKey', 'sample secret key'],
                ['paymentMethodName', 'sample name'],
                ['captureMethod', 'sample method'],
                ['webhookAccessId', 'sample uuid', false],
                ['webhookStripeId', 'sample id'],
                ['webhookSecret', 'sample secret'],
                ['reAuthorizationEnabled', true],
                ['reAuthorizationEmail', 'email@example.org'],
                ['userMonitoringEnabled', true],
            ]
        );

        $stripeTransportSettings = new StripePaymentElementSettings();

        self::assertPropertyCollections(
            $stripeTransportSettings,
            [
                ['paymentMethodLabels', new LocalizedFallbackValue()],
                ['paymentMethodShortLabels', new LocalizedFallbackValue()],
            ]
        );
    }

    public function testGetSettingsBag(): void
    {
        $labels = new ArrayCollection([(new LocalizedFallbackValue())->setString('Stripe Payment Element')]);
        $shortLabels = new ArrayCollection([(new LocalizedFallbackValue())->setString('Stripe')]);

        /** @var StripePaymentElementSettings $entity */
        $entity = $this->getEntity(
            StripePaymentElementSettings::class,
            [
                'apiPublicKey' => 'sample public key',
                'apiSecretKey' => 'sample secret key',
                'paymentMethodName' => 'sample name',
                'paymentMethodLabels' => $labels,
                'paymentMethodShortLabels' => $shortLabels,
                'captureMethod' => 'sample action',
                'webhookAccessId' => 'sample uuid',
                'webhookStripeId' => 'sample id',
                'webhookSecret' => 'sample secret',
                'reAuthorizationEnabled' => true,
                'reAuthorizationEmail' => 'email@example.org',
                'userMonitoringEnabled' => true,
            ]
        );

        $result = $entity->getSettingsBag();

        self::assertEquals('sample public key', $result->get('api_public_key'));
        self::assertEquals('sample secret key', $result->get('api_secret_key'));
        self::assertEquals('sample name', $result->get('payment_method_name'));
        self::assertEquals($result->get('payment_method_labels'), $labels);
        self::assertEquals($result->get('payment_method_short_labels'), $shortLabels);
        self::assertEquals('sample action', $result->get('capture_method'));
        self::assertEquals('sample uuid', $result->get('webhook_access_id'));
        self::assertEquals('sample id', $result->get('webhook_stripe_id'));
        self::assertEquals('sample secret', $result->get('webhook_secret'));
        self::assertTrue($result->get('re_authorization_enabled'));
        self::assertEquals('email@example.org', $result->get('re_authorization_email'));
        self::assertTrue($result->get('user_monitoring_enabled'));
    }
}
