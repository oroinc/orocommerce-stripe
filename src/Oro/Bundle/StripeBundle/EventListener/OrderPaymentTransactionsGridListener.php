<?php

namespace Oro\Bundle\StripeBundle\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;

/**
 * Add 'reference' column to order payment transaction grid if Stripe used as payment method.
 */
class OrderPaymentTransactionsGridListener
{
    private ManagerRegistry $managerRegistry;
    private StripePaymentConfigsProvider $paymentProvider;

    public function __construct(ManagerRegistry $managerRegistry, StripePaymentConfigsProvider $paymentProvider)
    {
        $this->managerRegistry = $managerRegistry;
        $this->paymentProvider = $paymentProvider;
    }

    public function onBuildBefore(BuildBefore $event): void
    {
        $config = $event->getConfig();
        $dataGrid = $event->getDatagrid();

        if ($this->isStripePaymentTransaction($dataGrid)) {
            $config->addColumn(
                'reference',
                [
                    'label' => 'oro.payment.paymenttransaction.reference.label'
                ],
                'payment_transaction.reference'
            );
        }
    }

    private function isStripePaymentTransaction(DatagridInterface $dataGrid): bool
    {
        $orderId = $dataGrid->getParameters()->get('order_id', null);

        if (!$orderId) {
            return false;
        }

        $orderPaymentMethods = $this->getOrderPaymentMethods($orderId);
        $activeStripePaymentMethods = $this->getActiveStripePaymentMethods();
        $usedStripeMethods = array_intersect($activeStripePaymentMethods, $orderPaymentMethods);
        return count($usedStripeMethods);
    }

    private function getActiveStripePaymentMethods(): array
    {
        $stripePaymentMethods = $this->paymentProvider->getConfigs();
        return array_keys($stripePaymentMethods);
    }

    private function getOrderPaymentMethods(int $orderId): array
    {
        $repository = $this->managerRegistry->getRepository(PaymentTransaction::class);
        $paymentMethods = $repository->getPaymentMethods(Order::class, [$orderId]);

        return $paymentMethods[$orderId] ?? [];
    }
}
