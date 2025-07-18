<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeClient;

use Stripe\StripeClient;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Creates an instance of {@see StripeClient}.
 */
class StripeClientFactory implements StripeClientFactoryInterface, ResetInterface
{
    /** @var array<string,LoggingStripeClient> */
    private array $stripeClient = [];

    public function __construct(private readonly array $stripeClientDefaultConfig = [])
    {
    }

    /**
     * @throws \JsonException
     */
    #[\Override]
    public function createStripeClient(
        StripeClientConfigInterface $stripeConfig
    ): StripeClient&LoggingStripeClientInterface {
        $stripeClientConfig = $stripeConfig->getStripeClientConfig() + $this->stripeClientDefaultConfig;
        $key = md5(json_encode($stripeClientConfig, JSON_THROW_ON_ERROR));

        $this->stripeClient[$key] ??= new LoggingStripeClient($stripeClientConfig);

        return $this->stripeClient[$key];
    }

    #[\Override]
    public function reset(): void
    {
        $this->stripeClient = [];
    }
}
