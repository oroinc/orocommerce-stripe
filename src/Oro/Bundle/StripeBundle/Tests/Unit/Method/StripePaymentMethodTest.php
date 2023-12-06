<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method;

use LogicException;
use Oro\Bundle\PaymentBundle\Context\PaymentContext;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Client\Response\PurchaseResponse;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionRegistry;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PurchasePaymentAction;
use Oro\Bundle\StripeBundle\Method\StripePaymentMethod;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StripePaymentMethodTest extends TestCase
{
    protected StripePaymentMethod $method;

    protected PaymentActionRegistry|MockObject $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(PaymentActionRegistry::class);
        $config = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test'
        ]);

        $this->method = new StripePaymentMethod($config, $this->registry);
    }

    public function testIsApplicableSuccess(): void
    {
        $this->assertTrue($this->method->isApplicable(new PaymentContext([
            PaymentContext::FIELD_TOTAL => 0.5
        ])));
    }

    public function testIsApplicableFailed(): void
    {
        $this->assertFalse($this->method->isApplicable(new PaymentContext([
            PaymentContext::FIELD_TOTAL => 0.4
        ])));
    }

    public function testGetIdentifier(): void
    {
        $this->assertEquals('test', $this->method->getIdentifier());
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupports(string $method, bool $expected): void
    {
        $this->assertEquals($expected, $this->method->supports($method));
    }

    public function supportsDataProvider(): array
    {
        return [
            [PaymentMethodInterface::PURCHASE, true],
            [PaymentActionInterface::CONFIRM_ACTION, true],
            [PaymentMethodInterface::CAPTURE, true],
            [PaymentMethodInterface::CANCEL, true],
            [PaymentMethodInterface::REFUND, true],
            [PaymentMethodInterface::INVOICE, false],
        ];
    }

    public function testExecuteFailed(): void
    {
        $registry = new PaymentActionRegistry([]);
        $config = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test'
        ]);

        $method = new StripePaymentMethod($config, $registry);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Payment action test is not supported');
        $method->execute('test', new PaymentTransaction());
    }

    public function testExecuteSuccess(): void
    {
        $transaction = new PaymentTransaction();
        $config = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test'
        ]);

        $responseObject = new PaymentIntentResponse([
            'id' => 'pi_1',
            'status' => 'succeeded',
            'amount' => 2022,
            'requires_action' => false,
            'payment_intent_client_secret' => 'secret'
        ]);

        $response = new PurchaseResponse($responseObject);

        $action = $this->createMock(PurchasePaymentAction::class);
        $action
            ->expects($this->once())
            ->method('execute')
            ->with($config, $transaction)
            ->willReturn($response);

        $this->registry
            ->expects($this->once())
            ->method('getPaymentAction')
            ->with('test', $transaction)
            ->willReturn($action);

        $result = $this->method->execute('test', $transaction);

        $expected = [
            'successful' => true,
            'requires_action' => false,
            'payment_intent_client_secret' => null //Return null because no any user actions required.
        ];

        $this->assertEquals($expected, $result);
    }

    public function testExecuteWithApiError(): void
    {
        $transaction = new PaymentTransaction();
        $config = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test'
        ]);

        $action = $this->createMock(PurchasePaymentAction::class);
        $action
            ->expects($this->once())
            ->method('execute')
            ->with($config, $transaction)
            ->willThrowException(new StripeApiException('message', 'code', 'decline'));

        $this->registry
            ->expects($this->once())
            ->method('getPaymentAction')
            ->with('test', $transaction)
            ->willReturn($action);

        $result = $this->method->execute('test', $transaction);

        $expected = [
            'successful' => false,
            'error' => 'message',
            'message' => 'message',
            'stripe_error_code' => 'code',
            'decline_code' => 'decline'
        ];

        $this->assertEquals($expected, $result);
    }
}
