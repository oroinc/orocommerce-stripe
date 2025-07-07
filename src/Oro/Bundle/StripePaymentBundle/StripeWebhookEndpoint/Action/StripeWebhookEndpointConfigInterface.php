<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action;

/**
 * Interface for configuration model for working with Stripe WebhookEndpoints API.
 */
interface StripeWebhookEndpointConfigInterface
{
    /**
     * @return string ID to associate incoming webhooks to a specific Stripe integration.
     */
    public function getWebhookAccessId(): string;

    /**
     * @return string URL for the WebhookEndpoint in Stripe.
     */
    public function getWebhookUrl(): string;

    /**
     * @return string|null ID of the WebhookEndpoint in Stripe.
     */
    public function getWebhookStripeId(): ?string;

    /**
     * @return string|null Secret of the WebhookEndpoint in Stripe.
     */
    public function getWebhookSecret(): ?string;

    /**
     * @return string Description of the WebhookEndpoint in Stripe.
     */
    public function getWebhookDescription(): string;

    /**
     * @return array<string> List of the enabled events of the WebhookEndpoint in Stripe.
     */
    public function getWebhookEvents(): array;

    /**
     * @return array<string,string|int|float|bool|null> Metadata of the WebhookEndpoint in Stripe.
     */
    public function getWebhookMetadata(): array;
}
