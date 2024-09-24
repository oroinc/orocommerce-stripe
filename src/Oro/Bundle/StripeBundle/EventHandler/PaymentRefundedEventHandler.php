<?php

namespace Oro\Bundle\StripeBundle\EventHandler;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\EventHandler\Exception\StripeEventHandleException;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\CollectionResponseInterface;
use Oro\Bundle\StripeBundle\Model\PaymentIntentAwareInterface;
use Oro\Bundle\StripeBundle\Model\RefundResponse;
use Oro\Bundle\StripeBundle\Model\RefundResponseInterface;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Handle refund event data.
 */
class PaymentRefundedEventHandler extends AbstractStripeEventHandler implements StripeEventHandlerInterface
{
    private const PAYMENT_REFUNDED_EVENT = 'charge.refunded';
    private StripeGatewayFactoryInterface $stripeClientFactory;
    private ?StripeGatewayInterface $client = null;
    private array $sourceCaptureTransactions = [];
    private array $sourceAuthorizeTransactions = [];

    public function __construct(
        ManagerRegistry $managerRegistry,
        PaymentTransactionProvider $paymentTransactionProvider,
        StripeGatewayFactoryInterface $stripeClientFactory
    ) {
        parent::__construct($managerRegistry, $paymentTransactionProvider);
        $this->stripeClientFactory = $stripeClientFactory;
    }

    #[\Override]
    public function handle(StripeEventInterface $event): void
    {
        $chargeResponseObject = $event->getData();
        $paymentMethodIdentifier = $event->getPaymentMethodIdentifier();
        $paymentConfig = $event->getPaymentConfig();

        if (!$chargeResponseObject instanceof PaymentIntentAwareInterface) {
            throw new \LogicException(
                sprintf('Unexpected response type object. It should be of %s type', PaymentIntentAwareInterface::class)
            );
        }

        $paymentIntentId = $chargeResponseObject->getPaymentIntentId();
        // Only captured payments could be refunded.
        $sourceTransaction = $this->getSourceTransaction($paymentIntentId, $paymentMethodIdentifier);

        if (!$sourceTransaction) {
            // If the `authorize` transaction exists, return the status - Response::HTTP_OK,
            // since this is not considered an error.
            $authorizeTransaction = $this->getAuthorizeSourceTransaction($paymentIntentId, $paymentMethodIdentifier);
            if ($authorizeTransaction) {
                return;
            }

            throw new StripeEventHandleException('`Payment could not be refunded. There are no capture transaction`');
        }

        $refundTransactions = $this->getPaymentTransactionRepository()->findBy([
            'sourcePaymentTransaction' => $sourceTransaction,
            'action' => PaymentMethodInterface::REFUND
        ]);

        $refundIsInProcess = false;
        $existingTransactions = [];

        // Check if refund has been initiated from Oro application and is in progress now.
        foreach ($refundTransactions as $transaction) {
            if ($this->isInProgress($transaction)) {
                $refundIsInProcess = true;
                break;
            }

            $existingTransactions[] = $transaction->getReference();
        }

        // Check if transaction has not been already created by API Call.
        if (!$refundIsInProcess) {
            // Get all refunds for the current charge.
            $refundsCollection = $this->getChargeRefunds($chargeResponseObject, $paymentConfig);

            /** @var RefundResponse $refundResponseObject */
            foreach ($refundsCollection as $refundResponseObject) {
                // Create refund transaction if it does not exists in ORO application.
                if (!in_array($refundResponseObject->getIdentifier(), $existingTransactions)) {
                    $this->createPaymentTransaction($refundResponseObject, $paymentMethodIdentifier);
                }
            }
        }
    }

    #[\Override]
    protected function createPaymentTransaction(
        ResponseObjectInterface $responseObject,
        string $paymentMethodIdentifier
    ): void {
        $sourceReference = $responseObject->getPaymentIntentId();
        $sourceTransaction = $this->getSourceTransaction($sourceReference, $paymentMethodIdentifier);

        $refundPaymentTransaction = $this->paymentTransactionProvider->createPaymentTransactionByParentTransaction(
            PaymentMethodInterface::REFUND,
            $sourceTransaction
        );

        $refundPaymentTransaction->setActive(false);

        if ($responseObject instanceof RefundResponseInterface) {
            $refundPaymentTransaction->setAmount($responseObject->getRefundedAmount());
        }

        $this->updateAndSaveTransaction(
            $responseObject,
            $refundPaymentTransaction,
            $sourceTransaction
        );
    }

    #[\Override]
    public function isSupported(StripeEventInterface $event): bool
    {
        return $event->getEventName() === self::PAYMENT_REFUNDED_EVENT;
    }

    private function getSourceTransaction(string $reference, string $paymentMethod): ?PaymentTransaction
    {
        if (!array_key_exists($reference, $this->sourceCaptureTransactions)) {
            $this->sourceCaptureTransactions[$reference] = $this->findSourceTransaction(
                $reference,
                PaymentMethodInterface::CAPTURE,
                $paymentMethod
            );
        }

        return $this->sourceCaptureTransactions[$reference];
    }

    private function getAuthorizeSourceTransaction(string $reference, string $paymentMethod): ?PaymentTransaction
    {
        if (!array_key_exists($reference, $this->sourceAuthorizeTransactions)) {
            $this->sourceAuthorizeTransactions[$reference] = $this->findSourceTransaction(
                $reference,
                PaymentMethodInterface::AUTHORIZE,
                $paymentMethod
            );
        }

        return $this->sourceAuthorizeTransactions[$reference];
    }

    private function isInProgress(PaymentTransaction $transaction): bool
    {
        return $transaction->isActive() && !$transaction->isSuccessful() && !$transaction->getReference();
    }

    private function getApiClient(StripePaymentConfig $paymentConfig): StripeGatewayInterface
    {
        if (null === $this->client) {
            $this->client = $this->stripeClientFactory->create($paymentConfig);
        }

        return $this->client;
    }

    private function getChargeRefunds(
        ResponseObjectInterface $responseObject,
        StripePaymentConfig $paymentConfig
    ): CollectionResponseInterface {
        $criteria = [
            'charge' => $responseObject->getIdentifier()
        ];

        return $this->getApiClient($paymentConfig)->getAllRefunds($criteria);
    }
}
