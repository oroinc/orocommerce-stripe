<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Action\PaymentTransactionPartialRefundAction;
use Oro\Component\ConfigExpression\ContextAccessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Routing\RouterInterface;

class PaymentTransactionPartialRefundActionTest extends TestCase
{
    private ContextAccessor&MockObject $contextAccessor;
    private PaymentMethodProviderInterface&MockObject $paymentMethodProvider;
    private PaymentTransactionProvider&MockObject $paymentTransactionProvider;
    private RouterInterface&MockObject $router;
    private EventDispatcherInterface&MockObject $dispatcher;
    private LoggerInterface&MockObject $logger;
    private PaymentTransactionPartialRefundAction $action;

    #[\Override]
    protected function setUp(): void
    {
        $this->contextAccessor = $this->createMock(ContextAccessor::class);
        $this->paymentMethodProvider = $this->createMock(PaymentMethodProviderInterface::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->action = new PaymentTransactionPartialRefundAction(
            $this->contextAccessor,
            $this->paymentMethodProvider,
            $this->paymentTransactionProvider,
            $this->router
        );
        $this->action->setLogger($this->logger);
        $this->action->setDispatcher($this->dispatcher);
    }

    /**
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $data, array $expected): void
    {
        /** @var PaymentTransaction $capturePaymentTransaction */
        $capturePaymentTransaction = $data['options']['paymentTransaction'];
        /** @var PaymentTransaction $refundPaymentTransaction */
        $refundPaymentTransaction = $data['refundPaymentTransaction'];
        $options = $data['options'];
        $context = [];

        $this->contextAccessor->expects(self::any())
            ->method('getValue')
            ->willReturnArgument(1);

        $this->paymentTransactionProvider->expects(self::once())
            ->method('createPaymentTransactionByParentTransaction')
            ->with(PaymentMethodInterface::REFUND, $capturePaymentTransaction)
            ->willReturn($refundPaymentTransaction);

        if ($data['response'] instanceof \Exception) {
            $responseValue = $this->throwException($data['response']);
        } else {
            $responseValue = $this->returnValue($data['response']);
        }

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->expects(self::once())
            ->method('execute')
            ->with(PaymentMethodInterface::REFUND, $refundPaymentTransaction)
            ->will($responseValue);

        $this->paymentMethodProvider->expects(self::any())
            ->method('hasPaymentMethod')
            ->with($capturePaymentTransaction->getPaymentMethod())
            ->willReturn(true);

        $this->paymentMethodProvider->expects(self::once())
            ->method('getPaymentMethod')
            ->with($capturePaymentTransaction->getPaymentMethod())
            ->willReturn($paymentMethod);

        $this->paymentTransactionProvider->expects(self::exactly(2))
            ->method('savePaymentTransaction')
            ->withConsecutive(
                [$refundPaymentTransaction],
                [$capturePaymentTransaction]
            );

        $this->contextAccessor->expects(self::once())
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

    /**
     * @dataProvider executeWrongOptionsDataProvider
     */
    public function testExecuteWrongOptions(array $options): void
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

    public function testExecuteFailedWhenPaymentMethodNotExists(): void
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

        $this->paymentMethodProvider->expects(self::once())
            ->method('hasPaymentMethod')
            ->willReturn(false);

        $this->contextAccessor->expects(self::any())
            ->method('getValue')
            ->willReturnArgument(1);

        $this->contextAccessor->expects(self::once())
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

    private function getPaymentTransaction(string $action, bool $successful): PaymentTransaction
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod('testPaymentMethodType');
        $paymentTransaction->setAction($action);
        $paymentTransaction->setAmount(100.00);
        $paymentTransaction->setActive(true);
        $paymentTransaction->setSuccessful($successful);

        return $paymentTransaction;
    }
}
