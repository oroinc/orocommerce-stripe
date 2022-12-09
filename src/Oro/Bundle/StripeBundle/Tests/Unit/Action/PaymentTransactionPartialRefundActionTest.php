<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Tests\Unit\Action\AbstractActionTest;
use Oro\Bundle\StripeBundle\Action\PaymentTransactionPartialRefundAction;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\PropertyAccess\PropertyPath;

class PaymentTransactionPartialRefundActionTest extends AbstractActionTest
{
    use EntityTrait;

    protected function getAction()
    {
        return new PaymentTransactionPartialRefundAction(
            $this->contextAccessor,
            $this->paymentMethodProvider,
            $this->paymentTransactionProvider,
            $this->router
        );
    }

    /**
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $data, array $expected)
    {
        /** @var PaymentTransaction $capturePaymentTransaction */
        $capturePaymentTransaction = $data['options']['paymentTransaction'];
        /** @var PaymentTransaction $refundPaymentTransaction */
        $refundPaymentTransaction = $data['refundPaymentTransaction'];
        $options = $data['options'];
        $context = [];

        $this->contextAccessor
            ->expects($this->any())
            ->method('getValue')
            ->will($this->returnArgument(1));

        $this->paymentTransactionProvider
            ->expects($this->once())
            ->method('createPaymentTransactionByParentTransaction')
            ->with(PaymentMethodInterface::REFUND, $capturePaymentTransaction)
            ->willReturn($refundPaymentTransaction);

        if ($data['response'] instanceof \Exception) {
            $responseValue = $this->throwException($data['response']);
        } else {
            $responseValue = $this->returnValue($data['response']);
        }

        /** @var PaymentMethodInterface|MockObject $paymentMethod */
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->expects($this->once())
            ->method('execute')
            ->with(PaymentMethodInterface::REFUND, $refundPaymentTransaction)
            ->will($responseValue);

        $this->paymentMethodProvider->expects($this->any())
            ->method('hasPaymentMethod')
            ->with($capturePaymentTransaction->getPaymentMethod())
            ->willReturn(true);

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with($capturePaymentTransaction->getPaymentMethod())
            ->willReturn($paymentMethod);

        $this->paymentTransactionProvider
            ->expects($this->exactly(2))
            ->method('savePaymentTransaction')
            ->withConsecutive(
                [$refundPaymentTransaction],
                [$capturePaymentTransaction]
            );

        $this->contextAccessor
            ->expects($this->once())
            ->method('setValue')
            ->with($context, $options['attribute'], $expected);

        $this->action->initialize($options);
        $this->action->execute($context);
    }

    public function executeDataProvider(): array
    {
        return [
            'default' => [
                'data' => [
                    'refundPaymentTransaction' => $this->getPaymentTransaction(PaymentMethodInterface::REFUND, true),
                    'options' => [
                        'paymentTransaction' => $this->getPaymentTransaction(PaymentMethodInterface::CAPTURE, true),
                        'attribute' => new PropertyPath('test'),
                        'transactionOptions' => [
                            'testOption' => 'testOption',
                        ],
                        'amount' => 50.00
                    ],
                    'response' => ['testResponse' => 'testResponse'],
                ],
                'expected' => [
                    'transaction' => null,
                    'successful' => true,
                    'message' => null,
                    'testOption' => 'testOption',
                    'testResponse' => 'testResponse',
                ],
            ],
            'throw exception' => [
                'data' => [
                    'refundPaymentTransaction' => $this->getPaymentTransaction(PaymentMethodInterface::REFUND, false),
                    'options' => [
                        'paymentTransaction' => $this->getPaymentTransaction(PaymentMethodInterface::CAPTURE, true),
                        'attribute' => new PropertyPath('test'),
                        'transactionOptions' => [
                            'testOption' => 'testOption',
                        ],
                        'amount' => 50.00
                    ],
                    'response' => new \Exception(),
                ],
                'expected' => [
                    'transaction' => null,
                    'successful' => false,
                    'message' => 'oro.payment.message.error',
                    'testOption' => 'testOption',
                ],
            ],
        ];
    }

    private function getPaymentTransaction(string $action, bool $successful): PaymentTransaction
    {
        return $this->getEntity(PaymentTransaction::class, [
            'action' => $action,
            'amount' => 100.00,
            'active' => true,
            'successful' => $successful,
            'paymentMethod' => 'testPaymentMethodType'
        ]);
    }

    /**
     * @dataProvider executeWrongOptionsDataProvider
     */
    public function testExecuteWrongOptions(array $options)
    {
        $this->expectException(UndefinedOptionsException::class);
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod('stripePaymentMethodType');

        $this->action->initialize($options);
        $this->action->execute([]);
    }

    public function executeWrongOptionsDataProvider(): array
    {
        return [
            [['someOption' => 'someValue']],
            [['object' => 'someValue']],
            [['currency' => 'someCurrency']],
            [['paymentMethod' => 'somePaymentMethod']],
        ];
    }

    public function testExecuteFailedWhenPaymentMethodNotExists()
    {
        $context = [];
        $options = [
            'paymentTransaction' => new PaymentTransaction(),
            'attribute' => new PropertyPath('test'),
            'transactionOptions' => [
                'testOption' => 'testOption',
            ],
            'amount' => 100.00
        ];

        $this->paymentMethodProvider->expects($this->once())
            ->method('hasPaymentMethod')
            ->willReturn(false);

        $this->contextAccessor->expects($this->any())
            ->method('getValue')
            ->will($this->returnArgument(1));

        $this->contextAccessor
            ->expects($this->once())
            ->method('setValue')
            ->with(
                $context,
                $options['attribute'],
                [
                    'transaction' => null,
                    'successful' => false,
                    'message' => 'oro.payment.message.error',
                    'testOption' => 'testOption',
                ]
            );

        $this->action->initialize($options);
        $this->action->execute($context);
    }
}
