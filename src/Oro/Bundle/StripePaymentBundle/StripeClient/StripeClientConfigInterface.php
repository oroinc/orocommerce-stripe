<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeClient;

/**
 * Interface for Stripe API Client configuration.
 */
interface StripeClientConfigInterface
{
    /**
     * @return array<string,mixed>
     *
     * Configuration settings include the following options:
     *
     *  - api_key (null|string): the Stripe API key, to be used in regular API requests.
     *  - client_id (null|string): the Stripe client ID, to be used in OAuth requests.
     *  - stripe_account (null|string): a Stripe account ID. If set, all requests sent by the client
     *    will automatically use the {@code Stripe-Account} header with that account ID.
     *  - stripe_version (null|string): a Stripe API version. If set, all requests sent by the client
     *    will include the {@code Stripe-Version} header with that API version.
     *
     *  The following configuration settings are also available, though setting these should rarely be necessary:
     *
     *  - api_base (string): the base URL for regular API requests. Defaults to {@link StripeClient::DEFAULT_API_BASE}
     *  - connect_base (string): the base URL for OAuth requests. Defaults to {@link StripeClient::DEFAULT_CONNECT_BASE}
     *  - files_base (string): the base URL for file creation requests. Defaults to
     *     {@link StripeClient::DEFAULT_FILES_BASE}.
     */
    public function getStripeClientConfig(): array;

    public function getApiVersion(): string;

    public function getApiSecretKey(): string;

    public function getApiPublicKey(): string;
}
