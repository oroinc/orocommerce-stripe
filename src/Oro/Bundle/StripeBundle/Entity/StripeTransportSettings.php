<?php

namespace Oro\Bundle\StripeBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Stripe settings entity. Stores basic configuration options for Stripe Integration.
 *
 * @ORM\Entity(repositoryClass="Oro\Bundle\StripeBundle\Entity\Repository\StripeTransportSettingsRepository")
 */
class StripeTransportSettings extends Transport
{
    public const LABELS = 'labels';
    public const SHORT_LABELS = 'short_labels';
    public const API_PUBLIC_KEY = 'public_key';
    public const API_SECRET_KEY = 'secret_key';
    public const USER_MONITORING = 'user_monitoring';
    public const PAYMENT_ACTION = 'payment_action';
    public const SIGNING_SECRET = 'signing_secret';
    public const ALLOW_RE_AUTHORIZE = 'allow_re_authorize';
    public const RE_AUTHORIZATION_ERROR_EMAIL = 're_authorization_error_email';

    protected ?ParameterBag $settings = null;

    /**
     * @ORM\ManyToMany(
     *      targetEntity="Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue",
     *      cascade={"ALL"},
     *      orphanRemoval=true
     * )
     * @ORM\JoinTable(
     *      name="oro_stripe_transport_label",
     *      joinColumns={
     *          @ORM\JoinColumn(name="transport_id", referencedColumnName="id", onDelete="CASCADE")
     *      },
     *      inverseJoinColumns={
     *          @ORM\JoinColumn(name="localized_value_id", referencedColumnName="id", onDelete="CASCADE", unique=true)
     *      }
     * )
     */
    protected ?Collection $labels = null;

    /**
     * @ORM\ManyToMany(
     *      targetEntity="Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue",
     *      cascade={"ALL"},
     *      orphanRemoval=true
     * )
     * @ORM\JoinTable(
     *      name="oro_stripe_transport_short_label",
     *      joinColumns={
     *          @ORM\JoinColumn(name="transport_id", referencedColumnName="id", onDelete="CASCADE")
     *      },
     *      inverseJoinColumns={
     *          @ORM\JoinColumn(name="localized_value_id", referencedColumnName="id", onDelete="CASCADE", unique=true)
     *      }
     * )
     */
    protected ?Collection $shortLabels = null;

    /**
     * @ORM\Column(name="stripe_api_public_key", type="string", length=255, nullable=true)
     */
    protected ?string $apiPublicKey = null;

    /**
     * @ORM\Column(name="stripe_api_secret_key", type="crypted_string", length=255, nullable=true)
     */
    protected ?string $apiSecretKey = null;

    /**
     * @ORM\Column(name="stripe_signing_secret", type="string", length=255, nullable=true)
     */
    protected ?string $signingSecret = null;

    /**
     * @ORM\Column(name="stripe_payment_action", type="string", length=255, nullable=true)
     */
    protected ?string $paymentAction = null;

    /**
     * @ORM\Column(name="stripe_user_monitoring", type="boolean", length=255, nullable=true, options={"default"=true})
     */
    protected ?bool $userMonitoring = false;

    protected ?bool $supportPartialCapture = true;

    /**
     * @ORM\Column(name="stripe_enable_re_authorize", type="boolean", nullable=true, options={"default"=true})
     */
    protected ?bool $enableReAuthorize = true;

    /**
     * @ORM\Column(name="stripe_re_authorization_error_email", type="string", length=255, nullable=true)
     */
    protected ?string $reAuthorizationErrorEmail = null;

    public function __construct()
    {
        $this->labels = new ArrayCollection();
        $this->shortLabels = new ArrayCollection();
    }

    public function getSettingsBag(): ParameterBag
    {
        if (null === $this->settings) {
            $this->settings = new ParameterBag([
                self::LABELS => $this->getLabels(),
                self::SHORT_LABELS => $this->getShortLabels(),
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

    public function setReAuthorizationErrorEmail(string $reAuthorizationErrorEmail): self
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
