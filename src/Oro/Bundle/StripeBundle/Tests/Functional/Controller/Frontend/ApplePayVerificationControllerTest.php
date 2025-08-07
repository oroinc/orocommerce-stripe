<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\Controller\Frontend;

use Oro\Bundle\ConfigBundle\Tests\Functional\Traits\ConfigManagerAwareTestTrait;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class ApplePayVerificationControllerTest extends WebTestCase
{
    use ConfigManagerAwareTestTrait;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->initClient();
    }

    public function testApplePayDomainVerificationAction()
    {
        $configManager = self::getConfigManager();
        $configManager->set(
            'oro_stripe.apple_pay_domain_verification',
            'Apple Pay domain verification file content'
        );
        $configManager->flush();
        try {
            $this->client->request(
                'GET',
                '/.well-known/apple-developer-merchantid-domain-association'
            );
            $response = $this->client->getResponse();
        } finally {
            $configManager->set('oro_stripe.apple_pay_domain_verification', null);
            $configManager->flush();
        }

        self::assertResponseStatusCodeEquals($response, 200);
        self::assertEquals('Apple Pay domain verification file content', $response->getContent());
    }

    public function testApplePayDomainVerificationActionWithoutConfiguration()
    {
        $this->client->request(
            'GET',
            '/.well-known/apple-developer-merchantid-domain-association'
        );

        $response = $this->client->getResponse();

        self::assertHtmlResponseStatusCodeEquals($response, 404);
        self::assertEquals(
            'Apple Pay domain verification file data not found in system config.',
            $response->getContent()
        );
    }
}
