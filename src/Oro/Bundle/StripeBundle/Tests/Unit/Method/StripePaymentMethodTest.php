<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method;

use LogicException;
use Oro\Bundle\PaymentBundle\Context\PaymentContext;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Response\PurchaseResponse;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionRegistry;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PurchasePaymentAction;
use Oro\Bundle\StripeBundle\Method\StripePaymentMethod;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\TestCase;

class StripePaymentMethodTest extends TestCase
{
    private StripePaymentMethod $method;

    /** @var PaymentActionRegistry|\PHPUnit\Framework\MockObject\MockObject  */
    private PaymentActionRegistry $registry;

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

    public function testSupports(): void
    {
        $this->assertTrue($this->method->supports(PaymentMethodInterface::PURCHASE));
        $this->assertTrue($this->method->supports(PaymentActionInterface::CONFIRM_ACTION));
        $this->assertTrue($this->method->supports(PaymentMethodInterface::CAPTURE));
        $this->assertFalse($this->method->supports('test'));
    }

    public function testExecuteFailed(): void
    {
        $registry = new PaymentActionRegistry([]);
        $config = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test'
        ]);

        $method = new StripePaymentMethod($config, $registry);

        $this->expectException(LogicException::class);
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
            ->with('test')
            ->willReturn($action);

        $result = $this->method->execute('test', $transaction);

        $expected = [
            'successful' => true,
            'requires_action' => false,
            'payment_intent_client_secret' => null //Return null because no any user actions required.
        ];

        $this->assertEquals($expected, $result);
    }
}
