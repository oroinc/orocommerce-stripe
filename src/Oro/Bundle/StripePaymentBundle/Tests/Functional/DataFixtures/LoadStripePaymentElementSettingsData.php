<?php

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Component\DependencyInjection\ContainerAwareInterface;
use Oro\Component\DependencyInjection\ContainerAwareTrait;

class LoadStripePaymentElementSettingsData extends AbstractFixture implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public const string STRIPE_PAYMENT_ELEMENT_SETTINGS_1 = 'stripe_payment_element_settings_1';
    public const string STRIPE_PAYMENT_ELEMENT_SETTINGS_2 = 'stripe_payment_element_settings_2';
    public const string STRIPE_PAYMENT_ELEMENT_SETTINGS_3 = 'stripe_payment_element_settings_3';

    private const array SETTINGS_DATA = [
        self::STRIPE_PAYMENT_ELEMENT_SETTINGS_1 => [
            'paymentMethodName' => 'Stripe Payment Element 1',
            'paymentMethodLabel' => 'Stripe Payment 1',
            'paymentMethodShortLabel' => 'Stripe 1',
            'apiPublicKey' => 'pk_111_public',
            'apiSecretKey' => 'pk_111_secret',
            'reAuthorizationEmail' => 'email@example.org',
        ],
        self::STRIPE_PAYMENT_ELEMENT_SETTINGS_2 => [
            'paymentMethodName' => 'Stripe Payment Element 2',
            'paymentMethodLabel' => 'Stripe Payment 2',
            'paymentMethodShortLabel' => 'Stripe 2',
            'apiPublicKey' => 'pk_222_public',
            'apiSecretKey' => 'pk_222_secret',
        ],
        self::STRIPE_PAYMENT_ELEMENT_SETTINGS_3 => [
            'paymentMethodName' => 'Stripe Payment Element 3',
            'paymentMethodLabel' => 'Stripe Payment 3',
            'paymentMethodShortLabel' => 'Stripe 3',
            'apiPublicKey' => 'pk_333_public',
            'apiSecretKey' => 'pk_333_secret',
        ],
    ];

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        foreach (self::SETTINGS_DATA as $reference => $data) {
            $entity = new StripePaymentElementSettings();
            $entity->setPaymentMethodName($data['paymentMethodName']);
            $entity->addPaymentMethodLabel($this->createLocalizedValue($data['paymentMethodLabel']));
            $entity->addPaymentMethodShortLabel($this->createLocalizedValue($data['paymentMethodShortLabel']));
            $entity->setApiPublicKey($this->encryptData($data['apiPublicKey']));
            $entity->setApiSecretKey($this->encryptData($data['apiSecretKey']));
            $entity->setReAuthorizationEmail($data['reAuthorizationEmail'] ?? '');

            $manager->persist($entity);

            $this->setReference($reference, $entity);
        }

        $manager->flush();
    }

    private function createLocalizedValue(string $string): LocalizedFallbackValue
    {
        return (new LocalizedFallbackValue())->setString($string);
    }

    private function encryptData(string $data): string
    {
        return $this->container->get('oro_security.encoder.default')->encryptData($data);
    }
}
