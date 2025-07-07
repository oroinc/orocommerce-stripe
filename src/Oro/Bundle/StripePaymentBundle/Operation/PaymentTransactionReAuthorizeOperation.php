<?php

namespace Oro\Bundle\StripePaymentBundle\Operation;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\ActionBundle\Model\AbstractOperationService;
use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\LocaleBundle\Formatter\NumberFormatter;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\UIBundle\Tools\FlashMessageHelper;

/**
 * Service implementation of the `oro_stripe_payment_payment_transaction_re_authorize` operation.
 */
class PaymentTransactionReAuthorizeOperation extends AbstractOperationService
{
    public function __construct(
        private readonly ReAuthorizationExecutorInterface $reAuthorizationExecutor,
        private readonly NumberFormatter $numberFormatter,
        private readonly FlashMessageHelper $flashMessageHelper
    ) {
    }

    #[\Override]
    public function isPreConditionAllowed(ActionData $data, ?Collection $errors = null): bool
    {
        $this->assertEntity($data->getEntity());
        /** @var PaymentTransaction $paymentTransaction */
        $paymentTransaction = $data->getEntity();

        $data->offsetSet(
            'amountWithCurrency',
            $this->numberFormatter->formatCurrency($paymentTransaction->getAmount(), $paymentTransaction->getCurrency())
        );

        return $this->reAuthorizationExecutor->isApplicable($paymentTransaction);
    }

    #[\Override]
    public function execute(ActionData $data): void
    {
        $this->assertEntity($data->getEntity());
        /** @var PaymentTransaction $paymentTransaction */
        $paymentTransaction = $data->getEntity();

        $result = $this->reAuthorizationExecutor->reAuthorizeTransaction($paymentTransaction);
        $data->offsetSet('result', $result);

        if ($result['successful']) {
            $this->flashMessageHelper->addFlashMessage(
                'success',
                'oro.stripe_payment.operation.re_authorize.confirmation.success',
                ['%amount%' => $data->offsetGet('amountWithCurrency')]
            );
        } else {
            $this->flashMessageHelper->addFlashMessage(
                'error',
                $result['error'] ?? 'oro.stripe_payment.operation.re_authorize.confirmation.error',
                ['%amount%' => $data->offsetGet('amountWithCurrency')]
            );
        }
    }

    private function assertEntity(mixed $paymentTransaction): void
    {
        if (!$paymentTransaction instanceof PaymentTransaction) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Action entity is expected to be an instance of %s, got %s',
                    PaymentTransaction::class,
                    get_debug_type($paymentTransaction)
                )
            );
        }
    }
}
