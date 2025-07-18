<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Form\EventSubscriber;

use Oro\Bundle\FormBundle\Utils\FormUtils;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigFactory;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\CreateOrUpdateStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\DeleteStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor\StripeWebhookEndpointActionExecutorInterface;
use Oro\Bundle\UIBundle\Tools\UrlHelper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds the "webhookCreate" form field.
 * Handles the creation/update/deletion of Stripe webhook endpoint.
 */
final class StripeWebhookEndpointEventSubscriber implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripePaymentElementConfigFactory $stripePaymentElementConfigFactory,
        private readonly StripeWebhookEndpointActionExecutorInterface $stripeWebhookEndpointActionExecutor,
        private readonly UrlHelper $urlHelper,
        private readonly TranslatorInterface $translator,
        private readonly string $applicableChannelType
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
            FormEvents::POST_SUBMIT => 'onPostSubmit',
        ];
    }

    public function onPreSetData(PreSetDataEvent $event): void
    {
        /** @var Channel|null $channel */
        $channel = $event->getData();
        if ($channel?->getType() !== $this->applicableChannelType) {
            return;
        }

        /** @var StripePaymentElementSettings $stripePaymentElementSettings */
        $stripePaymentElementSettings = $channel->getTransport();
        if (empty($stripePaymentElementSettings?->getId())) {
            $isWebhookCreateEnabled = !$this->urlHelper->isLocalUrl();
        } else {
            $isWebhookCreateEnabled = !empty($stripePaymentElementSettings?->getWebhookStripeId());
        }

        $transportForm = $event->getForm()->get('transport');

        $this->addWebhookCreateField($transportForm, $isWebhookCreateEnabled);
    }

    private function addWebhookCreateField(
        FormInterface $transportForm,
        bool $isWebhookCreateEnabled
    ): void {
        $transportForm->add(
            'webhookCreate',
            CheckboxType::class,
            [
                'label' => 'oro.stripe_payment.payment_element.webhook_create.label',
                'tooltip' => 'oro.stripe_payment.payment_element.webhook_create.tooltip',
                'required' => false,
                'mapped' => false,
                'data' => $isWebhookCreateEnabled,
                'block_prefix' => 'oro_stripe_payment_element_settings_webhook_create',
            ]
        );
    }

    public function onPreSubmit(PreSubmitEvent $event): void
    {
        $channelType = $event->getData()['type'] ?? '';
        if ($channelType !== $this->applicableChannelType) {
            return;
        }

        $transportForm = $event->getForm()->get('transport');

        $this->addWebhookCreateField($transportForm, false);

        if ($event->getData()['transport']['webhookCreate'] ?? false) {
            FormUtils::replaceField(
                $transportForm,
                'webhookSecret',
                ['data' => ''],
                ['constraints']
            );
        }
    }

    public function onPostSubmit(PostSubmitEvent $event): void
    {
        /** @var Channel|null $channel */
        $channel = $event->getData();
        if ($channel?->getType() !== $this->applicableChannelType) {
            return;
        }

        /** @var StripePaymentElementSettings|null $stripePaymentElementSettings */
        $stripePaymentElementSettings = $channel->getTransport();
        if (!$stripePaymentElementSettings instanceof StripePaymentElementSettings) {
            return;
        }

        if (!$event->getForm()->isValid()) {
            return;
        }

        $transportForm = $event->getForm()->get('transport');
        $webhookCreateField = $transportForm->get('webhookCreate');

        if (!$webhookCreateField->getData()) {
            $this->handleWebhookDeletion($stripePaymentElementSettings, $webhookCreateField);

            return;
        }

        $this->handleWebhookCreateOrUpdate($stripePaymentElementSettings, $webhookCreateField);
    }

    private function handleWebhookDeletion(
        StripePaymentElementSettings $stripePaymentElementSettings,
        FormInterface $webhookCreateField
    ): void {
        $stripePaymentElementConfig = $this->stripePaymentElementConfigFactory->createConfig(
            $stripePaymentElementSettings
        );

        $deleteStripeActionResult = $this->stripeWebhookEndpointActionExecutor->executeAction(
            new DeleteStripeWebhookEndpointAction($stripePaymentElementConfig)
        );

        if (!$deleteStripeActionResult->isSuccessful()) {
            $stripeError = $deleteStripeActionResult->getStripeError();

            $this->addFormError(
                $webhookCreateField,
                'oro.stripe_payment.webhook_endpoint.delete.error.message',
                $stripeError?->getMessage() ?? 'N/A'
            );

            $this->logError(
                'Failed to delete Stripe webhook endpoint for {settings_class} #{settings_id}: {message}',
                $stripePaymentElementSettings,
                $stripeError
            );
        }

        $stripePaymentElementSettings->setWebhookStripeId(null);
    }

    private function handleWebhookCreateOrUpdate(
        StripePaymentElementSettings $stripePaymentElementSettings,
        FormInterface $webhookCreateField
    ): void {
        $stripePaymentElementConfig = $this->stripePaymentElementConfigFactory->createConfig(
            $stripePaymentElementSettings
        );

        $createOrUpdateStripeActionResult = $this->stripeWebhookEndpointActionExecutor->executeAction(
            new CreateOrUpdateStripeWebhookEndpointAction($stripePaymentElementConfig)
        );

        $stripeWebhookEndpoint = $createOrUpdateStripeActionResult->getStripeObject();
        if ($createOrUpdateStripeActionResult->isSuccessful()) {
            if ($stripeWebhookEndpoint !== null) {
                $stripePaymentElementSettings->setWebhookStripeId($stripeWebhookEndpoint->id);

                if (!empty($stripeWebhookEndpoint->secret)) {
                    $stripePaymentElementSettings->setWebhookSecret($stripeWebhookEndpoint->secret);
                }
            }
        } else {
            $stripeError = $createOrUpdateStripeActionResult->getStripeError();

            $this->addFormError(
                $webhookCreateField,
                'oro.stripe_payment.webhook_endpoint.create_update.error.message',
                $stripeError?->getMessage() ?? 'N/A'
            );

            $this->logError(
                'Failed to create/update Stripe webhook endpoint for {settings_class} #{settings_id}: {message}',
                $stripePaymentElementSettings,
                $stripeError
            );
        }
    }

    private function addFormError(FormInterface $field, string $messageKey, string $message): void
    {
        $formError = new FormError($this->translator->trans($messageKey, ['%message%' => $message]));
        $field->addError($formError);
    }

    private function logError(
        string $logMessage,
        StripePaymentElementSettings $stripePaymentElementSettings,
        ?\Throwable $exception
    ): void {
        $this->logger->error(
            $logMessage,
            [
                'settings_class' => get_class($stripePaymentElementSettings),
                'settings_id' => $stripePaymentElementSettings->getId() ?? 'NEW',
                'message' => $exception?->getMessage() ?? 'N/A',
                'exception' => $exception,
            ]
        );
    }
}
