<?php

namespace Oro\Bundle\StripeBundle\Handler;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Notification\ReAuthorizeMessageNotifications;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Cancel existing expiring authorization transaction and create new one to extend its expiration time.
 */
class ReAuthorizationHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_CANCELLATION_REASON = 'abandoned';
    private const SUPPORTED_REASONS = ['duplicate', 'fraudulent', 'requested_by_customer', 'abandoned'];

    private EntitiesTransactionsProvider $transactionsProvider;
    private PaymentMethodProviderInterface $paymentMethodProvider;
    private PaymentTransactionProvider $paymentTransactionProvider;
    private StripePaymentConfigsProvider $paymentConfigsProvider;
    private ReAuthorizeMessageNotifications $messageNotifications;

    private string $cancellationReason = self::DEFAULT_CANCELLATION_REASON;

    public function __construct(
        EntitiesTransactionsProvider $transactionsProvider,
        PaymentMethodProviderInterface $paymentMethodProvider,
        PaymentTransactionProvider $paymentTransactionProvider,
        StripePaymentConfigsProvider $paymentConfigsProvider,
        ReAuthorizeMessageNotifications $messageNotification
    ) {
        $this->transactionsProvider = $transactionsProvider;
        $this->paymentMethodProvider = $paymentMethodProvider;
        $this->paymentTransactionProvider = $paymentTransactionProvider;
        $this->paymentConfigsProvider = $paymentConfigsProvider;
        $this->messageNotifications = $messageNotification;
        $this->logger = new NullLogger();
    }

    public function reAuthorize(): void
    {
        /** @var StripePaymentConfig[] $configs */
        $configs = array_filter(
            (array)$this->paymentConfigsProvider->getConfigs(),
            static fn(StripePaymentConfig $paymentConfig) => $paymentConfig->isReAuthorizationAllowed()
        );

        if (empty($configs)) {
            return;
        }

        $stripePaymentMethods = array_keys($configs);
        $paymentMethods = [];

        $expiringAuthorizationTransactions = $this->transactionsProvider
            ->getExpiringAuthorizationTransactions($stripePaymentMethods);
        foreach ($expiringAuthorizationTransactions as $authorizeTransaction) {
            $this->logger->info('Re-authorizing transaction ' . $authorizeTransaction->getId());
            $paymentMethodId = $authorizeTransaction->getPaymentMethod();

            if (!array_key_exists($paymentMethodId, $paymentMethods)) {
                $paymentMethods[$paymentMethodId] = $this->paymentMethodProvider->getPaymentMethod($paymentMethodId);
            }

            $paymentMethod = $paymentMethods[$paymentMethodId];
            $this->reAuthorizeSingleTransaction($authorizeTransaction, $paymentMethod, $configs[$paymentMethodId]);
        }
    }

    public function setCancellationReason(string $cancellationReason): void
    {
        if (!in_array($cancellationReason, self::SUPPORTED_REASONS, true)) {
            throw new \RuntimeException('Unsupported cancellation reason passed');
        }

        $this->cancellationReason = $cancellationReason;
    }

    private function reAuthorizeSingleTransaction(
        PaymentTransaction $authorizeTransaction,
        PaymentMethodInterface $paymentMethod,
        StripePaymentConfig $config
    ): void {
        try {
            $cancelTransaction = $this->createCancelTransaction($authorizeTransaction);
            $cancelResponse = $paymentMethod->execute(PaymentMethodInterface::CANCEL, $cancelTransaction);
            $this->paymentTransactionProvider->savePaymentTransaction($cancelTransaction);

            if (!$cancelTransaction->isSuccessful()) {
                $this->logger->warning(
                    'Unable to cancel existing authorization transaction',
                    [
                        'transaction' => $cancelTransaction,
                        'response' => $cancelResponse
                    ]
                );

                $error = $cancelResponse['error'] ?? 'Transaction cancellation failed.';
                $this->messageNotifications->sendAuthorizationFailed(
                    $cancelTransaction,
                    $config->getReAuthorizationErrorEmail(),
                    $error
                );

                return;
            }

            $authorizeTransaction->setActive(false);
            $this->paymentTransactionProvider->savePaymentTransaction($authorizeTransaction);

            $newAuthorizeTransaction = $this->createNewAuthorizationTransaction($authorizeTransaction);
            $newAuthorizeResponse = $paymentMethod->execute(
                PaymentMethodInterface::AUTHORIZE,
                $newAuthorizeTransaction
            );
            $newAuthorizeTransaction->setActive($newAuthorizeTransaction->isSuccessful());
            if (!$newAuthorizeTransaction->isSuccessful()) {
                $this->logger->warning(
                    'Unable to create new authorization transaction',
                    [
                        'transaction' => $newAuthorizeTransaction,
                        'response' => $newAuthorizeResponse
                    ]
                );

                $error = $newAuthorizeResponse['error'] ?? 'Unable to re-authorize payment.';
                $this->messageNotifications->sendAuthorizationFailed(
                    $newAuthorizeTransaction,
                    $config->getReAuthorizationErrorEmail(),
                    $error
                );
            }

            $this->paymentTransactionProvider->savePaymentTransaction($newAuthorizeTransaction);
        } catch (\Exception $e) {
            $this->logger->error(
                'Uncaught exception during transaction re-authorization',
                [
                    'exception' => $e
                ]
            );
        }
    }

    private function createCancelTransaction(PaymentTransaction $authorizeTransaction): PaymentTransaction
    {
        $cancelTransaction = $this->paymentTransactionProvider->createPaymentTransactionByParentTransaction(
            PaymentMethodInterface::CANCEL,
            $authorizeTransaction
        );
        $cancelTransaction->setTransactionOptions(array_merge(
            $cancelTransaction->getTransactionOptions(),
            ['cancelReason' => $this->cancellationReason]
        ));

        return $cancelTransaction;
    }

    private function createNewAuthorizationTransaction(PaymentTransaction $authorizeTransaction): PaymentTransaction
    {
        $newAuthorizeTransaction = $this->paymentTransactionProvider->createPaymentTransactionByParentTransaction(
            PaymentMethodInterface::AUTHORIZE,
            $authorizeTransaction
        );
        $newAuthorizeTransaction->setActive(true);
        $newAuthorizeTransaction->setTransactionOptions($authorizeTransaction->getTransactionOptions());

        return $newAuthorizeTransaction;
    }
}
