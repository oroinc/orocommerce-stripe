<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Model;

use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use PHPUnit\Framework\TestCase;

class CustomerResponseTest extends TestCase
{
    public function testResponseObject()
    {
        $data = $this->getResponseTestData();
        $response = new CustomerResponse($data);

        $this->assertEquals('succeeded', $response->getStatus());
        $this->assertEquals('cus_MRY9puF3n8qnBE', $response->getIdentifier());

        $responseData = $response->getData();

        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals([
            'customerId' => 'cus_MRY9puF3n8qnBE'
        ], $responseData['data']);
    }

    private function getResponseTestData(): array
    {
        return [
            'id' => 'cus_MRY9puF3n8qnBE',
            'object' => 'customer',
            'address' => [
                'city' => 'Haines City',
                'country' => 'US',
                'line1' => '801 Scenic Hwy',
                'line2' => null,
                'postal_code' => '33844',
                'state' => 'Florida'
            ],
            'balance' => 0,
            'created' => 1663336640,
            'currency' => null,
            'default_source' => null,
            'delinquent' => false,
            'description' => null,
            'discount' => null,
            'email' => 'AmandaRCole@example.org',
            'invoice_prefix' => 'F61C5AE1',
            'invoice_settings' => [
                'custom_fields' => null,
                'default_payment_method' => null,
                'footer' => null,
                'rendering_options' => null
            ],
            'livemode' => false,
            'metadata' => [],
            'name' => 'Amanda Cole',
            'phone' => null,
            'preferred_locales' => [],
            'shipping' => null,
            'tax_exempt' => 'none',
            'test_clock' => null
        ];
    }
}
