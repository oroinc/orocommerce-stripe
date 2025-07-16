<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Response;

use Oro\Bundle\StripeBundle\Client\Response\MultiPurchaseResponse;
use PHPUnit\Framework\TestCase;

class MultiPurchaseResponseTest extends TestCase
{
    public function testResponse(): void
    {
        $response = new MultiPurchaseResponse();
        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->hasSuccessful());

        $response->setSuccessful(false);
        $response->setHasSuccessful(true);

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->hasSuccessful());

        $this->assertEquals(
            [
                'is_multi_transaction' => true,
                'successful' => false,
                'has_successful' => true
            ],
            $response->prepareResponse()
        );
    }
}
