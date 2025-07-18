<?php

namespace Oro\Bundle\StripePaymentBundle\Form\Type;

use Oro\Bundle\FormBundle\Form\Type\OroPlaceholderPasswordType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Oro\Bundle\StripePaymentBundle\Configuration\StripePaymentConfiguration;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The settings form for the Stripe Payment Element integration.
 */
final class StripePaymentElementSettingsType extends AbstractType
{
    public function __construct(
        private readonly StripePaymentElementConfigFactory $stripePaymentElementConfigFactory,
        private readonly StripePaymentConfiguration $stripePaymentConfiguration,
        private readonly EventSubscriberInterface $stripePaymentElementLabelsEventSubscriber
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('apiPublicKey', TextType::class, [
                'label' => 'oro.stripe_payment.payment_element.api_public_key.label',
                'required' => true,
            ])
            ->add('apiSecretKey', OroPlaceholderPasswordType::class, [
                'label' => 'oro.stripe_payment.payment_element.api_secret_key.label',
                'required' => true,
            ])
            ->add('paymentMethodName', TextType::class, [
                'label' => 'oro.stripe_payment.payment_element.payment_method_name.label',
                'tooltip' => 'oro.stripe_payment.payment_element.payment_method_name.tooltip',
                'required' => true,
            ])
            ->add('paymentMethodLabels', LocalizedFallbackValueCollectionType::class, [
                'label' => 'oro.stripe_payment.payment_element.payment_method_labels.label',
                'tooltip' => 'oro.stripe_payment.payment_element.payment_method_labels.tooltip',
                'required' => true,
                'entry_options' => ['constraints' => [new NotBlank()]],
            ])
            ->add('paymentMethodShortLabels', LocalizedFallbackValueCollectionType::class, [
                'label' => 'oro.stripe_payment.payment_element.payment_method_short_labels.label',
                'tooltip' => 'oro.stripe_payment.payment_element.payment_method_short_labels.tooltip',
                'required' => true,
                'entry_options' => ['constraints' => [new NotBlank()]],
            ])
            ->add('webhookAccessId', HiddenType::class, [
                'error_bubbling' => true,
            ])
            ->add('webhookSecret', OroPlaceholderPasswordType::class, [
                'label' => 'oro.stripe_payment.payment_element.webhook_secret.label',
                'tooltip' => 'oro.stripe_payment.payment_element.webhook_secret.tooltip',
                'required' => true,
                'block_prefix' => 'oro_stripe_payment_element_settings_webhook_secret',
            ])
            ->add('captureMethod', ChoiceType::class, [
                'label' => 'oro.stripe_payment.payment_element.capture_method.label',
                'tooltip' => 'oro.stripe_payment.payment_element.capture_method.tooltip',
                'choices' => [
                    'oro.stripe_payment.payment_element.capture_method.manual' => 'manual',
                    'oro.stripe_payment.payment_element.capture_method.automatic' => 'automatic',
                ],
                'required' => true,
                'block_prefix' => 'oro_stripe_payment_element_settings_capture_method',
            ])
            ->add('reAuthorizationEnabled', CheckboxType::class, [
                'label' => 'oro.stripe_payment.payment_element.re_authorization_enabled.label',
                'tooltip' => 'oro.stripe_payment.payment_element.re_authorization_enabled.tooltip',
                'required' => false,
            ])
            ->add('reAuthorizationEmail', TextType::class, [
                'label' => 'oro.stripe_payment.payment_element.re_authorization_email.label',
                'tooltip' => 'oro.stripe_payment.payment_element.re_authorization_email.tooltip',
                'required' => false,
            ])
            ->add('userMonitoringEnabled', CheckboxType::class, [
                'label' => 'oro.stripe_payment.payment_element.user_monitoring_enabled.label',
                'tooltip' => 'oro.stripe_payment.payment_element.user_monitoring_enabled.tooltip',
                'required' => false,
            ]);

        $builder->addEventSubscriber($this->stripePaymentElementLabelsEventSubscriber);
    }

    #[\Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var StripePaymentElementSettings|null $stripePaymentElementSettings */
        $stripePaymentElementSettings = $form->getData();
        if ($stripePaymentElementSettings !== null) {
            $stripePaymentElementConfig = $this->stripePaymentElementConfigFactory
                ->createConfig($stripePaymentElementSettings);
            $view->children['webhookSecret']->vars['webhook_endpoint_url'] =
                $stripePaymentElementConfig->getWebhookUrl();
        }

        $view->children['captureMethod']->vars['payment_method_types_with_manual_capture'] =
            $this->stripePaymentConfiguration->getPaymentMethodTypesWithManualCapture();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StripePaymentElementSettings::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'oro_stripe_payment_element_settings';
    }
}
