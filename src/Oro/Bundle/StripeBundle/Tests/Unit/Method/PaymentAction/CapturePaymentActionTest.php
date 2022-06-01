<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use LogicException;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\StripeClientFactory;
use Oro\Bundle\StripeBundle\Client\StripeGateway;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\CapturePaymentAction;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Tests\Unit\Utils\SetReflectionPropertyTrait;
use PHPUnit\Framework\TestCase;
use Stripe\Collection;
use Stripe\PaymentIntent;

class CapturePaymentActionTest extends TestCase
{
    use SetReflectionPropertyTrait;

    private CapturePaymentAction $action;

    /** @var StripeGateway|StripeGatewayInterface|\PHPUnit\Framework\MockObject\MockObject */
    private StripeGatewayInterface $client;
    /** @var PaymentTransactionProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $transactionProvider;

    protected function setUp(): void
    {
        $this->transactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->action = new CapturePaymentAction(new StripeClientFactory(), $this->transactionProvider);

        $this->client = $this->createMock(StripeGateway::class);
        $this->setProperty(CapturePaymentAction::class, $this->action, 'client', $this->client);
    }

    public function testIsApplicable(): void
    {
        $this->assertFalse($this->action->isApplicable('test'));
        $this->assertTrue($this->action->isApplicable(PaymentMethodInterface::CAPTURE));
    }

    public function testExecuteException(): void
    {
        $this->expectException(LogicException::class);
        $this->action->execute(new StripePaymentConfig(), new PaymentTransaction());
    }

    public function testExecuteSuccess(): void
    {
        $transaction = new PaymentTransaction();
        $transaction->setSourcePaymentTransaction(new PaymentTransaction());
        $transaction->setActive(true);

        $charges = new Collection();
        $charges->offsetSet('balance_transaction', 'test');

        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'succeeded',
            'charges' => $charges
        ]);

        $responseObject = new PaymentIntentResponse($paymentIntent->toArray());

        $this->client
            ->expects($this->once())
            ->method('capture')
            ->willReturn($responseObject);

        $this->transactionProvider->expects($this->once())
            ->method('savePaymentTransaction');

        $response = $this->action->execute(new StripePaymentConfig(), $transaction);

        $this->assertFalse($transaction->isActive());
        $this->assertTrue($transaction->isSuccessful());
        $this->assertEquals($transaction->getReference(), 'pi_1');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals([
            'successful' => true,
        ], $response->prepareResponse());

        $transactionResponseData = $transaction->getResponse();
        $this->assertArrayHasKey('source', $transactionResponseData);
        $this->assertArrayHasKey('paymentIntentId', $transactionResponseData);
        $this->assertArrayHasKey('data', $transactionResponseData);

        $this->assertEquals('Stripe API', $transactionResponseData['source']);
        $this->assertEquals('pi_1', $transactionResponseData['paymentIntentId']);
    }
}
