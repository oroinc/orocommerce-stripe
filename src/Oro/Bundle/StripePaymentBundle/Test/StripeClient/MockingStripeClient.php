<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Test\StripeClient;

use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Stripe\StripeObject;

/**
 * A mocking StripeClient suitable for using in tests.
 */
class MockingStripeClient extends LoggingStripeClient
{
    private static ?MockingStripeClient $instance = null;

    private static array $mockResponses = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    #[\Override]
    protected function doRequest($method, $path, $params, $opts): StripeObject
    {
        $response = array_shift(self::$mockResponses);
        if ($response instanceof \Exception) {
            throw $response;
        }

        return $response ?? new StripeObject();
    }

    #[\Override]
    public function reset(): void
    {
        self::$mockResponses = [];

        parent::reset();
    }

    public static function addMockResponse(StripeObject|\Exception $response): void
    {
        self::$mockResponses[] = $response;
    }
}
