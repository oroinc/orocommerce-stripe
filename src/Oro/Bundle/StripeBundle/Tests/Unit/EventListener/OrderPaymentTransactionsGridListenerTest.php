<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Datagrid;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\StripeBundle\EventListener\OrderPaymentTransactionsGridListener;
use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use PHPUnit\Framework\TestCase;

class OrderPaymentTransactionsGridListenerTest extends TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject  */
    private $managerRegistryMock;

    /** @var StripePaymentConfigsProvider|\PHPUnit\Framework\MockObject\MockObject  */
    private $paymentConfigProviderMock;

    /** @var PaymentTransactionRepository|\PHPUnit\Framework\MockObject\MockObject  */
    private $paymentTransactionRepositoryMock;
    private OrderPaymentTransactionsGridListener $listener;

    protected function setUp(): void
    {
        $this->managerRegistryMock = $this->createMock(ManagerRegistry::class);
        $this->paymentConfigProviderMock = $this->createMock(StripePaymentConfigsProvider::class);
        $this->paymentTransactionRepositoryMock = $this->createMock(PaymentTransactionRepository::class);
        $this->managerRegistryMock->expects($this->any())
            ->method('getRepository')
            ->willReturn($this->paymentTransactionRepositoryMock);

        $this->listener = new OrderPaymentTransactionsGridListener(
            $this->managerRegistryMock,
            $this->paymentConfigProviderMock
        );
    }

    public function testReferenceColumnAdded()
    {
        $config = $this->createMock(DatagridConfiguration::class);
        $event = $this->createEvent($config, ['order_id' => 1]);

        $this->paymentConfigProviderMock->expects($this->once())
            ->method('getConfigs')
            ->willReturn([
                'stripe_1' => new StripePaymentConfig([])
            ]);

        $this->paymentTransactionRepositoryMock->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn([
                1 => ['stripe_1']
            ]);

        $config->expects($this->once())
            ->method('addColumn');

        $this->listener->onBuildBefore($event);
    }

    public function testOrderHasAnotherPaymentMethod()
    {
        $config = $this->createMock(DatagridConfiguration::class);
        $event = $this->createEvent($config, ['order_id' => 1]);

        $this->paymentConfigProviderMock->expects($this->once())
            ->method('getConfigs')
            ->willReturn([
                'stripe_1' => new StripePaymentConfig([])
            ]);

        $this->paymentTransactionRepositoryMock->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn([
                1 => ['test_payment_1']
            ]);

        $config->expects($this->never())
            ->method('addColumn');

        $this->listener->onBuildBefore($event);
    }

    public function testWithNotConfiguredStripeMethods()
    {
        $config = $this->createMock(DatagridConfiguration::class);
        $event = $this->createEvent($config, ['order_id' => 1]);

        $this->paymentConfigProviderMock->expects($this->once())
            ->method('getConfigs')
            ->willReturn([]);

        $this->paymentTransactionRepositoryMock->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn([
                1 => ['stripe_1']
            ]);

        $config->expects($this->never())
            ->method('addColumn');

        $this->listener->onBuildBefore($event);
    }

    public function testOrderIdParameterEmpty()
    {
        $config = $this->createMock(DatagridConfiguration::class);
        $event = $this->createEvent($config, []);

        $this->paymentConfigProviderMock->expects($this->never())
            ->method('getConfigs');

        $this->paymentTransactionRepositoryMock->expects($this->never())
            ->method('getPaymentMethods');

        $config->expects($this->never())
            ->method('addColumn');

        $this->listener->onBuildBefore($event);
    }

    private function createEvent(DatagridConfiguration $config, array $params = []): BuildBefore
    {
        $dataGrid = new Datagrid('order-payment-transactions-grid', $config, new ParameterBag($params));
        return new BuildBefore($dataGrid, $config);
    }
}
