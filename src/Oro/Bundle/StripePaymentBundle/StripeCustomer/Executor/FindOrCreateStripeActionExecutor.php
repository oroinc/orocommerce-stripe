<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor;

use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\PaymentBundle\Context\Factory\TransactionPaymentContextFactoryInterface;
use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\Event\StripeCustomerActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\StripeCustomerActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResult;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResultInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Finds a Stripe Customer, creates if not exists.
 *
 * @link https://docs.stripe.com/api/customers
 */
class FindOrCreateStripeActionExecutor implements StripeCustomerActionExecutorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly TransactionPaymentContextFactoryInterface $transactionPaymentContextFactory,
        private readonly EntityNameResolver $entityNameResolver,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return $stripeActionName === FindOrCreateStripeCustomerAction::ACTION_NAME;
    }

    #[\Override]
    public function isApplicableForAction(StripeCustomerActionInterface $stripeAction): bool
    {
        return $this->isSupportedByActionName($stripeAction->getActionName());
    }

    #[\Override]
    public function executeAction(
        StripeCustomerActionInterface $stripeAction
    ): StripeCustomerActionResultInterface {
        $paymentTransaction = $stripeAction->getPaymentTransaction();

        $paymentContext = $this->transactionPaymentContextFactory->create($paymentTransaction);
        if ($paymentContext === null) {
            $this->logNoPaymentContext($paymentTransaction);

            return new StripeCustomerActionResult(successful: false);
        }

        $customerUser = $paymentContext->getCustomerUser();
        if ($customerUser === null) {
            $this->logNoCustomerUser($paymentTransaction, $paymentContext);

            return new StripeCustomerActionResult(successful: false);
        }

        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());
        $stripeClient->beginScopeFor($paymentTransaction);

        $searchRequestArgs = $this->prepareSearchRequestArgs($stripeAction, $paymentContext);
        $stripeCustomer = $stripeClient->customers->search(...$searchRequestArgs)->first();

        if ($stripeCustomer === null) {
            $createRequestArgs = $this->prepareCreateRequestArgs($stripeAction, $paymentContext);
            $stripeCustomer = $stripeClient->customers->create(...$createRequestArgs);
        }

        return new StripeCustomerActionResult(successful: true, stripeCustomer: $stripeCustomer);
    }

    private function logNoPaymentContext(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->error(
            'Failed to find or create a Stripe customer: cannot create a payment context '
            . 'from payment transaction #{paymentTransactionId}',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }

    private function logNoCustomerUser(
        PaymentTransaction $paymentTransaction,
        PaymentContextInterface $paymentContext
    ): void {
        $this->logger->error(
            'Failed to find or create a Stripe customer: customer user is not present in the payment context '
            . 'created from payment transaction #{paymentTransactionId}',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
                'paymentContext' => $paymentContext,
            ]
        );
    }

    private function prepareSearchRequestArgs(
        StripeCustomerActionInterface $stripeAction,
        PaymentContextInterface $paymentContext
    ): array {
        $customerUser = $paymentContext->getCustomerUser();

        $requestArgs = [['query' => sprintf("email:'%s'", addslashes($customerUser->getEmail()))]];
        $beforeRequestEvent = new StripeCustomerActionBeforeRequestEvent(
            $stripeAction,
            'customersSearch',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }

    private function prepareCreateRequestArgs(
        StripeCustomerActionInterface $stripeAction,
        PaymentContextInterface $paymentContext
    ): array {
        $customerUser = $paymentContext->getCustomerUser();
        $requestArgs = [
            [
                'email' => $customerUser->getEmail(),
                'name' => $this->entityNameResolver->getName($customerUser),
            ],
        ];

        $address = $paymentContext->getBillingAddress();
        if ($address) {
            $requestArgs[0]['address'] = array_filter([
                'city' => $address->getCity(),
                'country' => $address->getCountryIso2(),
                'line1' => $address->getStreet(),
                'line2' => $address->getStreet2(),
                'postal_code' => $address->getPostalCode(),
                'state' => $address->getRegionName(),
            ]);
        }

        $beforeRequestEvent = new StripeCustomerActionBeforeRequestEvent(
            $stripeAction,
            'customersCreate',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }
}
