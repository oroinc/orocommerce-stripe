<?php

namespace Oro\Bundle\StripePaymentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\SecurityBundle\DoctrineExtension\Dbal\Types\CryptedStringType;
use Oro\Bundle\SecurityBundle\Tools\UUIDGenerator;
use Oro\Bundle\StripePaymentBundle\Entity\Repository\StripePaymentElementSettingsRepository;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Stores settings for the Stripe Payment Element integration.
 */
#[ORM\Entity(repositoryClass: StripePaymentElementSettingsRepository::class)]
class StripePaymentElementSettings extends Transport
{
    protected ?ParameterBag $settings = null;

    #[ORM\Column(name: 'stripe_payment_element_api_public_key', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $apiPublicKey = null;

    #[ORM\Column(
        name: 'stripe_payment_element_api_secret_key',
        type: CryptedStringType::TYPE,
        length: 255,
        nullable: true
    )]
    protected ?string $apiSecretKey = null;

    #[ORM\Column(name: 'stripe_payment_element_payment_method_name', type: Types::STRING, length: 255, nullable: false)]
    protected ?string $paymentMethodName = null;

    #[ORM\ManyToMany(targetEntity: LocalizedFallbackValue::class, cascade: ['ALL'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'oro_stripe_payment_element_payment_method_label')]
    #[ORM\JoinColumn(name: 'transport_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'localized_value_id', referencedColumnName: 'id', unique: true, onDelete: 'CASCADE')]
    protected ?Collection $paymentMethodLabels = null;

    #[ORM\ManyToMany(targetEntity: LocalizedFallbackValue::class, cascade: ['ALL'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'oro_stripe_payment_element_payment_method_short_label')]
    #[ORM\JoinColumn(name: 'transport_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'localized_value_id', referencedColumnName: 'id', unique: true, onDelete: 'CASCADE')]
    protected ?Collection $paymentMethodShortLabels = null;

    #[ORM\Column(name: 'stripe_payment_element_capture_method', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $captureMethod = null;

    #[ORM\Column(name: 'stripe_payment_element_webhook_access_id', type: Types::GUID, length: 255, nullable: true)]
    protected ?string $webhookAccessId = null;

    #[ORM\Column(name: 'stripe_payment_element_webhook_stripe_id', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $webhookStripeId = null;

    #[ORM\Column(
        name: 'stripe_payment_element_webhook_secret',
        type: CryptedStringType::TYPE,
        length: 255,
        nullable: true
    )]
    protected ?string $webhookSecret = null;

    #[ORM\Column(
        name: 'stripe_payment_element_re_authorization_enabled',
        type: Types::BOOLEAN,
        nullable: true,
        options: ['default' => false]
    )]
    protected ?bool $reAuthorizationEnabled = false;

    #[ORM\Column(
        name: 'stripe_payment_element_re_authorization_email',
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    protected ?string $reAuthorizationEmail = null;

    #[ORM\Column(
        name: 'stripe_payment_element_user_monitoring_enabled',
        type: Types::BOOLEAN,
        nullable: true,
        options: ['default' => false]
    )]
    protected ?bool $userMonitoringEnabled = false;

    public function __construct()
    {
        $this->paymentMethodLabels = new ArrayCollection();
        $this->paymentMethodShortLabels = new ArrayCollection();
        $this->webhookAccessId = UUIDGenerator::v4();
    }

    #[\Override]
    public function getSettingsBag(): ParameterBag
    {
        if (null === $this->settings) {
            $this->settings = new ParameterBag([
                'api_public_key' => $this->getApiPublicKey(),
                'api_secret_key' => $this->getApiSecretKey(),
                'payment_method_name' => $this->getPaymentMethodName(),
                'payment_method_labels' => $this->getPaymentMethodLabels(),
                'payment_method_short_labels' => $this->getPaymentMethodShortLabels(),
                'webhook_access_id' => $this->getWebhookAccessId(),
                'webhook_stripe_id' => $this->getWebhookStripeId(),
                'webhook_secret' => $this->getWebhookSecret(),
                'capture_method' => $this->getCaptureMethod(),
                're_authorization_enabled' => $this->isReAuthorizationEnabled(),
                're_authorization_email' => $this->getReAuthorizationEmail(),
                'user_monitoring_enabled' => $this->isUserMonitoringEnabled(),
            ]);
        }

        return $this->settings;
    }

    public function getApiPublicKey(): ?string
    {
        return $this->apiPublicKey;
    }

    public function setApiPublicKey(?string $apiPublicKey): self
    {
        $this->apiPublicKey = $apiPublicKey;

        return $this;
    }

    public function getApiSecretKey(): ?string
    {
        return $this->apiSecretKey;
    }

    public function setApiSecretKey(?string $apiSecretKey): self
    {
        $this->apiSecretKey = $apiSecretKey;

        return $this;
    }

    public function getPaymentMethodName(): ?string
    {
        return $this->paymentMethodName;
    }

    public function setPaymentMethodName(?string $paymentMethodName): self
    {
        $this->paymentMethodName = $paymentMethodName;

        return $this;
    }

    public function getPaymentMethodLabels(): Collection
    {
        return $this->paymentMethodLabels;
    }

    public function addPaymentMethodLabel(LocalizedFallbackValue $label): self
    {
        if (!$this->paymentMethodLabels->contains($label)) {
            $this->paymentMethodLabels->add($label);
        }

        return $this;
    }

    public function removePaymentMethodLabel(LocalizedFallbackValue $label): self
    {
        if ($this->paymentMethodLabels->contains($label)) {
            $this->paymentMethodLabels->removeElement($label);
        }

        return $this;
    }

    public function getPaymentMethodShortLabels(): Collection
    {
        return $this->paymentMethodShortLabels;
    }

    public function addPaymentMethodShortLabel(LocalizedFallbackValue $label): self
    {
        if (!$this->paymentMethodShortLabels->contains($label)) {
            $this->paymentMethodShortLabels->add($label);
        }

        return $this;
    }

    public function removePaymentMethodShortLabel(LocalizedFallbackValue $label): self
    {
        if ($this->paymentMethodShortLabels->contains($label)) {
            $this->paymentMethodShortLabels->removeElement($label);
        }

        return $this;
    }

    public function getWebhookAccessId(): ?string
    {
        return $this->webhookAccessId;
    }

    public function setWebhookAccessId(?string $webhookAccessId): self
    {
        $this->webhookAccessId = $webhookAccessId;

        return $this;
    }

    public function getWebhookStripeId(): ?string
    {
        return $this->webhookStripeId;
    }

    public function setWebhookStripeId(?string $webhookStripeId): self
    {
        $this->webhookStripeId = $webhookStripeId;

        return $this;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    public function setWebhookSecret(?string $webhookSecret): self
    {
        $this->webhookSecret = $webhookSecret;

        return $this;
    }

    public function getCaptureMethod(): ?string
    {
        return $this->captureMethod;
    }

    public function setCaptureMethod(string $captureMethod): self
    {
        $this->captureMethod = $captureMethod;

        return $this;
    }

    public function isReAuthorizationEnabled(): ?bool
    {
        return $this->reAuthorizationEnabled;
    }

    public function setReAuthorizationEnabled(bool $reAuthorizationEnabled): self
    {
        $this->reAuthorizationEnabled = $reAuthorizationEnabled;

        return $this;
    }

    public function getReAuthorizationEmail(): ?string
    {
        return $this->reAuthorizationEmail;
    }

    public function setReAuthorizationEmail(?string $reAuthorizationEmail): self
    {
        $this->reAuthorizationEmail = $reAuthorizationEmail;

        return $this;
    }

    public function isUserMonitoringEnabled(): ?bool
    {
        return $this->userMonitoringEnabled;
    }

    public function setUserMonitoringEnabled(bool $userMonitoringEnabled): self
    {
        $this->userMonitoringEnabled = $userMonitoringEnabled;

        return $this;
    }

    public function isWebhookCreate(): bool
    {
        return $this->webhookCreate;
    }

    public function setWebhookCreate(bool $webhookCreate): void
    {
        $this->webhookCreate = $webhookCreate;
    }
}
