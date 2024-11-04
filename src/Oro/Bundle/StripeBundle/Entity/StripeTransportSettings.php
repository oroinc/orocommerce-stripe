<?php

namespace Oro\Bundle\StripeBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\StripeBundle\Entity\Repository\StripeTransportSettingsRepository;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Stripe settings entity. Stores basic configuration options for Stripe Integration.
 */
#[ORM\Entity(repositoryClass: StripeTransportSettingsRepository::class)]
class StripeTransportSettings extends Transport
{
    public const LABELS = 'labels';
    public const SHORT_LABELS = 'short_labels';
    public const APPLE_GOOGLE_PAY_LABELS = 'apple_google_pay_labels';
    public const API_PUBLIC_KEY = 'public_key';
    public const API_SECRET_KEY = 'secret_key';
    public const USER_MONITORING = 'user_monitoring';
    public const PAYMENT_ACTION = 'payment_action';
    public const SIGNING_SECRET = 'signing_secret';
    public const ALLOW_RE_AUTHORIZE = 'allow_re_authorize';
    public const RE_AUTHORIZATION_ERROR_EMAIL = 're_authorization_error_email';

    public const DEFAULT_APPLE_GOOGLE_PAY_LABEL = 'Apple Pay/Google Pay';

    protected ?ParameterBag $settings = null;

    /**
     * @var Collection<int, LocalizedFallbackValue>
     */
    #[ORM\ManyToMany(targetEntity: LocalizedFallbackValue::class, cascade: ['ALL'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'oro_stripe_transport_label')]
    #[ORM\JoinColumn(name: 'transport_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'localized_value_id', referencedColumnName: 'id', unique: true, onDelete: 'CASCADE')]
    protected ?Collection $labels = null;

    /**
     * @var Collection<int, LocalizedFallbackValue>
     */
    #[ORM\ManyToMany(targetEntity: LocalizedFallbackValue::class, cascade: ['ALL'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'oro_stripe_transport_short_label')]
    #[ORM\JoinColumn(name: 'transport_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'localized_value_id', referencedColumnName: 'id', unique: true, onDelete: 'CASCADE')]
    protected ?Collection $shortLabels = null;

    /**
     * @var Collection<int, LocalizedFallbackValue>
     */
    #[ORM\ManyToMany(targetEntity: LocalizedFallbackValue::class, cascade: ['ALL'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'oro_stripe_transport_apple_google_pay_label')]
    #[ORM\JoinColumn(name: 'transport_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'localized_value_id', referencedColumnName: 'id', unique: true, onDelete: 'CASCADE')]
    protected ?Collection $appleGooglePayLabels = null;

    #[ORM\Column(name: 'stripe_api_public_key', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $apiPublicKey = null;

    #[ORM\Column(name: 'stripe_api_secret_key', type: 'crypted_string', length: 255, nullable: true)]
    protected ?string $apiSecretKey = null;

    #[ORM\Column(name: 'stripe_signing_secret', type: 'crypted_string', length: 255, nullable: true)]
    protected ?string $signingSecret = null;

    #[ORM\Column(name: 'stripe_payment_action', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $paymentAction = null;

    #[ORM\Column(
        name: 'stripe_user_monitoring',
        type: Types::BOOLEAN,
        length: 255,
        nullable: true,
        options: ['default' => false]
    )]
    protected ?bool $userMonitoring = false;

    protected ?bool $supportPartialCapture = true;

    #[ORM\Column(
        name: 'stripe_enable_re_authorize',
        type: Types::BOOLEAN,
        nullable: true,
        options: ['default' => false]
    )]
    protected ?bool $enableReAuthorize = false;

    #[ORM\Column(name: 'stripe_re_authorization_error_email', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $reAuthorizationErrorEmail = null;

    public function __construct()
    {
        $this->labels = new ArrayCollection();
        $this->shortLabels = new ArrayCollection();

        $defaultAppleGooglePayLabel = new LocalizedFallbackValue();
        $defaultAppleGooglePayLabel->setString(self::DEFAULT_APPLE_GOOGLE_PAY_LABEL);
        $this->appleGooglePayLabels = new ArrayCollection([$defaultAppleGooglePayLabel]);
    }

    #[\Override]
    public function getSettingsBag(): ParameterBag
    {
        if (null === $this->settings) {
            $this->settings = new ParameterBag([
                self::LABELS => $this->getLabels(),
                self::SHORT_LABELS => $this->getShortLabels(),
                self::APPLE_GOOGLE_PAY_LABELS => $this->getAppleGooglePayLabels(),
                self::API_PUBLIC_KEY => $this->getApiPublicKey(),
                self::API_SECRET_KEY => $this->getApiSecretKey(),
                self::PAYMENT_ACTION => $this->getPaymentAction(),
                self::USER_MONITORING => $this->getUserMonitoring(),
                self::SIGNING_SECRET => $this->getSigningSecret(),
                self::ALLOW_RE_AUTHORIZE => $this->getEnableReAuthorize(),
                self::RE_AUTHORIZATION_ERROR_EMAIL => $this->getReAuthorizationErrorEmail()
            ]);
        }

        return $this->settings;
    }

    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(LocalizedFallbackValue $label): self
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
        }

        return $this;
    }

    public function removeLabel(LocalizedFallbackValue $label): self
    {
        if ($this->labels->contains($label)) {
            $this->labels->removeElement($label);
        }

        return $this;
    }

    public function getShortLabels(): Collection
    {
        return $this->shortLabels;
    }

    public function addShortLabel(LocalizedFallbackValue $shortLabel): self
    {
        if (!$this->shortLabels->contains($shortLabel)) {
            $this->shortLabels->add($shortLabel);
        }

        return $this;
    }

    public function removeShortLabel(LocalizedFallbackValue $shortLabel): self
    {
        if ($this->shortLabels->contains($shortLabel)) {
            $this->shortLabels->removeElement($shortLabel);
        }

        return $this;
    }

    public function getAppleGooglePayLabels(): Collection
    {
        return $this->appleGooglePayLabels;
    }

    public function addAppleGooglePayLabel(LocalizedFallbackValue $label): self
    {
        if (!$this->appleGooglePayLabels->contains($label)) {
            $this->appleGooglePayLabels->add($label);
        }

        return $this;
    }

    public function removeAppleGooglePayLabel(LocalizedFallbackValue $label): self
    {
        if ($this->appleGooglePayLabels->contains($label)) {
            $this->appleGooglePayLabels->removeElement($label);
        }

        return $this;
    }

    public function getApiPublicKey(): ?string
    {
        return $this->apiPublicKey;
    }

    public function setApiPublicKey(?string $apiPublicKey): StripeTransportSettings
    {
        $this->apiPublicKey = $apiPublicKey;
        return $this;
    }

    public function getApiSecretKey(): ?string
    {
        return $this->apiSecretKey;
    }

    public function setApiSecretKey(?string $apiSecretKey): StripeTransportSettings
    {
        $this->apiSecretKey = $apiSecretKey;
        return $this;
    }

    public function getSigningSecret(): ?string
    {
        return $this->signingSecret;
    }

    public function setSigningSecret(?string $signingSecret): StripeTransportSettings
    {
        $this->signingSecret = $signingSecret;
        return $this;
    }

    public function getPaymentAction(): ?string
    {
        return $this->paymentAction;
    }

    public function setPaymentAction(?string $paymentAction): StripeTransportSettings
    {
        $this->paymentAction = $paymentAction;
        return $this;
    }

    public function getUserMonitoring(): ?bool
    {
        return $this->userMonitoring;
    }

    public function setUserMonitoring(bool $userMonitoring): StripeTransportSettings
    {
        $this->userMonitoring = $userMonitoring;
        return $this;
    }

    public function getReAuthorizationErrorEmail(): ?string
    {
        return $this->reAuthorizationErrorEmail;
    }

    public function setReAuthorizationErrorEmail(?string $reAuthorizationErrorEmail): self
    {
        $this->reAuthorizationErrorEmail = $reAuthorizationErrorEmail;

        return $this;
    }

    public function getEnableReAuthorize(): ?bool
    {
        return $this->enableReAuthorize;
    }

    public function setEnableReAuthorize(bool $enableReAuthorize): self
    {
        $this->enableReAuthorize = $enableReAuthorize;

        return $this;
    }
}
