<?php

namespace Oro\Bundle\StripeBundle\Form\Type;

use Oro\Bundle\FormBundle\Form\Type\OroPlaceholderPasswordType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Form provides field to set basic Stripe Integration settings.
 */
class StripeSettingsType extends AbstractType
{
    public const BLOCK_PREFIX = 'oro_stripe_settings';

    protected TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('labels', LocalizedFallbackValueCollectionType::class, [
                'label'    => 'oro.stripe.settings.labels.label',
                'tooltip'  => 'oro.stripe.settings.labels.tooltip',
                'required' => true,
                'entry_options'  => [
                    'constraints' => [new NotBlank()]
                ]
            ])
            ->add('shortLabels', LocalizedFallbackValueCollectionType::class, [
                'label'    => 'oro.stripe.settings.short_labels.label',
                'tooltip'  => 'oro.stripe.settings.short_labels.tooltip',
                'required' => true,
                'entry_options'  => [
                    'constraints' => [new NotBlank()]
                ]
            ])
            ->add('apiPublicKey', TextType::class, [
                'label' => 'oro.stripe.settings.api_public_key.label',
                'required' => true,
                'constraints' => [new NotBlank()]
            ])
            ->add('apiSecretKey', OroPlaceholderPasswordType::class, [
                'label' => 'oro.stripe.settings.api_secret_key.label',
                'required' => true,
                'constraints' => [new NotBlank()]
            ])
            ->add('signingSecret', TextType::class, [
                'label' => 'oro.stripe.settings.signing_secret.label',
                'tooltip' => 'oro.stripe.settings.signing_secret.tooltip',
                'required' => true,
                'constraints' => [new NotBlank()]
            ])
            ->add('paymentAction', ChoiceType::class, [
                'choices' => ['manual', 'automatic'],
                'choice_label' => function ($action) {
                    return $this->translator->trans(sprintf('oro.stripe.settings.payment_action.%s', $action));
                },
                'label' => 'oro.stripe.settings.payment_action.label',
                'required' => true,
                'constraints' => [new NotBlank()]
            ])
            ->add('userMonitoring', CheckboxType::class, [
                'label' => 'oro.stripe.settings.user_monitoring.label',
                'tooltip' => 'oro.stripe.settings.user_monitoring.tooltip',
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StripeTransportSettings::class,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getBlockPrefix(): string
    {
        return self::BLOCK_PREFIX;
    }
}
