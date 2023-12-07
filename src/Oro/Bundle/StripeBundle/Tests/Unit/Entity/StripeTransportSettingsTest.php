<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Component\Testing\Unit\EntityTestCaseTrait;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;

class StripeTransportSettingsTest extends TestCase
{
    use EntityTestCaseTrait;
    use EntityTrait;

    public function testAccessors(): void
    {
        $this->assertPropertyAccessors(
            new StripeTransportSettings(),
            [
                ['apiPublicKey', 'some string'],
                ['apiSecretKey', 'some string'],
                ['paymentAction', 'some string'],
                ['userMonitoring', true],
                ['enableReAuthorize', true],
                ['reAuthorizationErrorEmail', 'test@test.com']
            ]
        );

        $stripeTransportSettings = new StripeTransportSettings();

        $this->assertPropertyCollections(
            $stripeTransportSettings,
            [
                ['labels', new LocalizedFallbackValue()],
                ['shortLabels', new LocalizedFallbackValue()]
            ]
        );

        $defaultAppleGooglePayLabel = new LocalizedFallbackValue();
        $defaultAppleGooglePayLabel->setString(StripeTransportSettings::DEFAULT_APPLE_GOOGLE_PAY_LABEL);

        $this->assertEquals(
            new ArrayCollection([
                $defaultAppleGooglePayLabel,
            ]),
            $stripeTransportSettings->getAppleGooglePayLabels()
        );
    }

    public function testGetSettingsBag(): void
    {
        $labels = new ArrayCollection([(new LocalizedFallbackValue())->setString('Stripe Payment')]);
        $shortLabels = new ArrayCollection([(new LocalizedFallbackValue())->setString('Stripe')]);
        $appleGooglePayLabels = new ArrayCollection([
            (new LocalizedFallbackValue())->setString(StripeTransportSettings::DEFAULT_APPLE_GOOGLE_PAY_LABEL)
        ]);

        /** @var StripeTransportSettings $entity */
        $entity = $this->getEntity(
            StripeTransportSettings::class,
            [
                'apiPublicKey' => 'some public key',
                'apiSecretKey' => 'some secret key',
                'paymentAction' => 'some payment action',
                'userMonitoring' => true,
                'enableReAuthorize' => true,
                'reAuthorizationErrorEmail' => 'test@test.com',
                'labels' => $labels,
                'shortLabels' => $shortLabels
            ]
        );

        /** @var ParameterBag $result */
        $result = $entity->getSettingsBag();

        $this->assertEquals('some public key', $result->get('public_key'));
        $this->assertEquals('some secret key', $result->get('secret_key'));
        $this->assertEquals('some payment action', $result->get('payment_action'));
        $this->assertTrue($result->get('user_monitoring'));
        $this->assertEquals($labels, $result->get('labels'));
        $this->assertEquals($shortLabels, $result->get('short_labels'));
        $this->assertEquals($appleGooglePayLabels, $result->get('apple_google_pay_labels'));
        $this->assertTrue($result->get('allow_re_authorize'));
        $this->assertEquals('test@test.com', $result->get('re_authorization_error_email'));

        $this->assertEquals('some public key', $entity->getApiPublicKey());
        $this->assertEquals('some secret key', $entity->getApiSecretKey());
        $this->assertEquals('some payment action', $entity->getPaymentAction());
        $this->assertTrue($entity->getUserMonitoring());
        $this->assertEquals($labels, $entity->getLabels());
        $this->assertEquals($shortLabels, $entity->getShortLabels());
        $this->assertEquals($appleGooglePayLabels, $entity->getAppleGooglePayLabels());
        $this->assertTrue($entity->getEnableReAuthorize());
        $this->assertEquals('test@test.com', $entity->getReAuthorizationErrorEmail());
    }
}
