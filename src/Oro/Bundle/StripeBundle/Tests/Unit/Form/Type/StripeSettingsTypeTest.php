<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Form\Type;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\FormBundle\Form\Extension\TooltipFormExtension;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Form\Type\FallbackPropertyType;
use Oro\Bundle\LocaleBundle\Form\Type\FallbackValueType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizationCollectionType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedPropertyType;
use Oro\Bundle\LocaleBundle\Tests\Unit\Form\Type\Stub\LocalizationCollectionTypeStub;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Form\Type\StripeSettingsType;
use Oro\Bundle\TranslationBundle\Translation\Translator;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\Testing\Unit\FormIntegrationTestCase;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;

class StripeSettingsTypeTest extends FormIntegrationTestCase
{
    use EntityTrait;

    public const LOCALIZATION_ID = 998;

    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject  */
    private ManagerRegistry $registry;

    /** @var Translator|\PHPUnit\Framework\MockObject\MockObject */
    private Translator $translator;

    protected function setUp(): void
    {
        $this->formType = new StripeSettingsType($this->getTranslator());
        parent::setUp();
    }

    /**
     * @return array
     */
    protected function getExtensions(): array
    {
        $repositoryLocalization = $this->createMock(ObjectRepository::class);
        $repositoryLocalization->expects($this->any())
            ->method('find')
            ->willReturnCallback(
                function ($id) {
                    return $this->getEntity(Localization::class, ['id' => $id]);
                }
            );

        $repositoryLocalizedFallbackValue = $this->createMock(ObjectRepository::class);
        $repositoryLocalizedFallbackValue->expects($this->any())
            ->method('find')
            ->willReturnCallback(
                function ($id) {
                    return $this->getEntity(LocalizedFallbackValue::class, ['id' => $id]);
                }
            );

        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->registry->expects($this->any())
            ->method('getRepository')
            ->willReturnMap(
                [
                    ['OroLocaleBundle:Localization', null, $repositoryLocalization],
                    ['OroLocaleBundle:LocalizedFallbackValue', null, $repositoryLocalizedFallbackValue],
                ]
            );

        /** @var ConfigProvider|\PHPUnit\Framework\MockObject\MockObject $entityConfigProvider */
        $entityConfigProvider = $this->createMock(ConfigProvider::class);

        $this->translator = $this->createMock(Translator::class);
        $stripeType = new StripeSettingsType($this->translator);

        return [
            new PreloadedExtension(
                [
                    $stripeType,
                    LocalizedPropertyType::class => new LocalizedPropertyType(),
                    LocalizedFallbackValueCollectionType::class => new LocalizedFallbackValueCollectionType(
                        $this->registry
                    ),
                    LocalizationCollectionType::class => new LocalizationCollectionTypeStub(
                        [
                            $this->getEntity(Localization::class, ['id' => self::LOCALIZATION_ID]),
                        ]
                    ),
                    FallbackValueType::class => new FallbackValueType(),
                    FallbackPropertyType::class => new FallbackPropertyType($this->translator),
                ],
                [
                    FormType::class => [
                        new TooltipFormExtension($entityConfigProvider, $this->translator),
                    ],
                ]
            ),
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    public function testGetBlockPrefixReturnsCorrectString(): void
    {
        $formType = new StripeSettingsType($this->translator);
        $this->assertEquals(StripeSettingsType::BLOCK_PREFIX, $formType->getBlockPrefix());
    }

    public function testSubmit(): void
    {
        $stripeSettings = (new StripeTransportSettings())
            ->setApiPublicKey('public key')
            ->setApiSecretKey('secret key')
            ->setPaymentAction('manual')
            ->addLabel($this->createLocalizedValue(
                'Label 1',
                null,
                $this->getEntity(Localization::class, ['id' => self::LOCALIZATION_ID])
            ))
            ->addLabel($this->createLocalizedValue('Label 1'))
            ->addShortLabel($this->createLocalizedValue(
                'Label 2',
                null,
                $this->getEntity(Localization::class, ['id' => self::LOCALIZATION_ID])
            ))
            ->addShortLabel($this->createLocalizedValue('Label 2'))
            ->setSigningSecret('secret')
            ->setEnableReAuthorize(true)
            ->setReAuthorizationErrorEmail('test@test.com');

        $submitData = [
            'apiPublicKey' => 'public key',
            'apiSecretKey' => 'secret key',
            'paymentAction' => 'manual',
            'userMonitoring' => false,
            'labels' => [
                'values' => [
                    'default' => 'Label 1',
                    'localizations' => [
                        self::LOCALIZATION_ID => [
                            'value' => 'Label 1',
                        ],
                    ],
                ],
            ],
            'shortLabels' => [
                'values' => [
                    'default' => 'Label 2',
                    'localizations' => [
                        self::LOCALIZATION_ID => [
                            'value' => 'Label 2',
                        ],
                    ],
                ],
            ],
            'signingSecret' => 'secret',
            'enableReAuthorize' => true,
            'reAuthorizationErrorEmail' => 'test@test.com'
        ];


        $form = $this->factory->create(StripeSettingsType::class);
        $form->submit($submitData);

        $this->assertTrue($form->isValid());
        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($stripeSettings, $form->getData());
    }

    public function testConfigureOptions(): void
    {
        $resolver = $this->createMock(OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with(['data_class' => StripeTransportSettings::class,]);

        $formType = new StripeSettingsType($this->translator);
        $formType->configureOptions($resolver);
    }

    protected function createLocalizedValue(
        ?string $string = null,
        ?string $text = null,
        ?Localization $localization = null
    ): LocalizedFallbackValue {
        $value = new LocalizedFallbackValue();
        $value->setString($string)
            ->setText($text)
            ->setLocalization($localization);

        return $value;
    }
}
