<?php

namespace Oro\Bundle\StripeBundle\EventHandler;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Basic methods for event handlers.
 */
abstract class AbstractStripeEventHandler
{
    protected ManagerRegistry $managerRegistry;
    protected PaymentTransactionProvider $paymentTransactionProvider;

    public function __construct(
        ManagerRegistry $managerRegistry,
        PaymentTransactionProvider $paymentTransactionProvider
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->paymentTransactionProvider = $paymentTransactionProvider;
    }

    public function handle(StripeEventInterface $event): void
    {
        $responseData = $event->getData();
        $paymentMethodIdentifier = $event->getPaymentMethodIdentifier();

        $this->createPaymentTransaction($responseData, $paymentMethodIdentifier);
    }

    protected function findSourceTransaction(
        string $reference,
        string $action,
        string $paymentMethod
    ): ?PaymentTransaction {
        return $this->getPaymentTransactionRepository()->findOneBy([
            'reference' => $reference,
            'action' => $action,
            'paymentMethod' => $paymentMethod,
            'successful' => true
        ]);
    }

    protected function getPaymentTransactionRepository(): PaymentTransactionRepository
    {
        return $this->managerRegistry->getRepository(PaymentTransaction::class);
    }

    protected function updateAndSaveTransaction(
        ResponseObjectInterface $responseObject,
        PaymentTransaction $paymentTransaction,
        ?PaymentTransaction $sourceTransaction
    ): void {
        $responseData = array_merge(
            ['source' => ResponseObjectInterface::ACTION_SOURCE_MANUALLY],
            $responseObject->getData()
        );

        $paymentTransaction->setReference($responseObject->getIdentifier());
        $paymentTransaction->setResponse($responseData);
        $paymentTransaction->setSuccessful(true);

        $this->paymentTransactionProvider->savePaymentTransaction($paymentTransaction);

        if (null !== $sourceTransaction) {
            $this->paymentTransactionProvider->savePaymentTransaction($sourceTransaction);
        }
    }

    /**
     * Logic of payment transaction creation could be different for different actions.
     */
    abstract protected function createPaymentTransaction(
        ResponseObjectInterface $responseObject,
        string $paymentMethodIdentifier
    ):void;
}
