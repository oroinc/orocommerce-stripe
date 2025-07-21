<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeCustomer\Executor;

use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\AddressBundle\Entity\Region;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Bundle\PaymentBundle\Context\Factory\TransactionPaymentContextFactoryInterface;
use Oro\Bundle\PaymentBundle\Context\PaymentContext;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\Event\StripeCustomerActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\StripeCustomerActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor\FindOrCreateStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResult;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Customer as StripeCustomer;
use Stripe\SearchResult as StripeSearchResult;
use Stripe\Service\CustomerService as StripeCustomerService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class FindOrCreateStripeActionExecutorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private FindOrCreateStripeActionExecutor $executor;

    private MockObject&TransactionPaymentContextFactoryInterface $transactionPaymentContextFactory;

    private MockObject&EntityNameResolver $entityNameResolver;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private MockObject&LoggingStripeClient $stripeClient;

    protected function setUp(): void
    {
        $stripeClientFactory = $this->createMock(StripeClientFactoryInterface::class);
        $this->transactionPaymentContextFactory = $this->createMock(TransactionPaymentContextFactoryInterface::class);
        $this->entityNameResolver = $this->createMock(EntityNameResolver::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new FindOrCreateStripeActionExecutor(
            $stripeClientFactory,
            $this->transactionPaymentContextFactory,
            $this->entityNameResolver,
            $this->eventDispatcher
        );
        $this->setUpLoggerMock($this->executor);

        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->stripeClient = $this->createMock(LoggingStripeClient::class);
        $stripeClientFactory
            ->method('createStripeClient')
            ->with($this->stripePaymentElementConfig)
            ->willReturn($this->stripeClient);
    }

    public function testIsSupportedByActionNameReturnsFalseWhenSupportedAction(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName('customer_find_or_create'));
    }

    public function testIsSupportedByActionNameReturnsFalseWhenNotSupportedAction(): void
    {
        self::assertFalse($this->executor->isSupportedByActionName('sample_action'));
    }

    public function testIsApplicableForActionReturnsFalseWhenNotSupportedActionName(): void
    {
        $stripeAction = $this->createMock(StripeCustomerActionInterface::class);
        $stripeAction
            ->expects(self::once())
            ->method('getActionName')
            ->willReturn('sample_action');

        $this->assertLoggerNotCalled();

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionReturnsTrue(): void
    {
        $stripeAction = new FindOrCreateStripeCustomerAction(
            $this->stripePaymentElementConfig,
            new PaymentTransaction()
        );

        $this->assertLoggerNotCalled();

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionWhenNoPaymentContext(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 42);

        $stripeAction = new FindOrCreateStripeCustomerAction(
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $this->transactionPaymentContextFactory
            ->expects(self::once())
            ->method('create')
            ->with($paymentTransaction)
            ->willReturn(null);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to find or create a Stripe customer: cannot create a payment context '
                . 'from payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                ]
            );

        self::assertEquals(
            new StripeCustomerActionResult(successful: false),
            $this->executor->executeAction($stripeAction)
        );
    }

    public function testExecuteActionWhenNoCustomerUser(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 42);

        $stripeAction = new FindOrCreateStripeCustomerAction(
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $paymentContext = new PaymentContext([]);
        $this->transactionPaymentContextFactory
            ->expects(self::once())
            ->method('create')
            ->with($paymentTransaction)
            ->willReturn($paymentContext);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to find or create a Stripe customer: customer user is not present in the payment context '
                . 'created from payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'paymentContext' => $paymentContext,
                ]
            );

        self::assertEquals(
            new StripeCustomerActionResult(successful: false),
            $this->executor->executeAction($stripeAction)
        );
    }

    public function testExecuteActionWhenStripeCustomerFound(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 42);

        $stripeAction = new FindOrCreateStripeCustomerAction(
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $customerUser = (new CustomerUser())->setEmail('email@example.org');
        $paymentContext = new PaymentContext([PaymentContext::FIELD_CUSTOMER_USER => $customerUser]);

        $this->transactionPaymentContextFactory
            ->expects(self::once())
            ->method('create')
            ->with($paymentTransaction)
            ->willReturn($paymentContext);

        $requestArgs = [['query' => sprintf("email:'%s'", addslashes($customerUser->getEmail()))]];

        $beforeRequestEvent = new StripeCustomerActionBeforeRequestEvent(
            $stripeAction,
            'customersSearch',
            $requestArgs
        );
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $stripeCustomer = new StripeCustomer();

        $searchResult = new StripeSearchResult();
        $searchResult->data = [$stripeCustomer];

        $this->stripeClient->customers = $this->createMock(StripeCustomerService::class);
        $this->stripeClient->customers
            ->expects(self::once())
            ->method('search')
            ->with(...$beforeRequestEvent->getRequestArgs())
            ->willReturn($searchResult);

        $this->assertLoggerNotCalled();

        self::assertEquals(
            new StripeCustomerActionResult(successful: true, stripeCustomer: $stripeCustomer),
            $this->executor->executeAction($stripeAction)
        );
    }

    public function testExecuteActionCreatesNewWithoutBillingAddressWhenStripeCustomerNotFound(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 42);

        $stripeAction = new FindOrCreateStripeCustomerAction(
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $customerUser = (new CustomerUser())->setEmail('email@example.org');
        $paymentContext = new PaymentContext([PaymentContext::FIELD_CUSTOMER_USER => $customerUser]);

        $this->transactionPaymentContextFactory
            ->expects(self::once())
            ->method('create')
            ->with($paymentTransaction)
            ->willReturn($paymentContext);

        $requestArgs = [['query' => sprintf("email:'%s'", addslashes($customerUser->getEmail()))]];

        $beforeSearchRequestEvent = new StripeCustomerActionBeforeRequestEvent(
            $stripeAction,
            'customersSearch',
            $requestArgs
        );

        $searchResult = new StripeSearchResult();
        $searchResult->data = [];

        $this->stripeClient->customers = $this->createMock(StripeCustomerService::class);
        $this->stripeClient->customers
            ->expects(self::once())
            ->method('search')
            ->with(...$beforeSearchRequestEvent->getRequestArgs())
            ->willReturn($searchResult);

        $customerUserName = 'Amanda Cole';
        $this->entityNameResolver
            ->expects(self::once())
            ->method('getName')
            ->with($customerUser)
            ->willReturn($customerUserName);

        $stripeCustomer = new StripeCustomer();

        $createRequestArgs = [
            [
                'email' => $customerUser->getEmail(),
                'name' => $customerUserName,
            ],
        ];

        $beforeCreateRequestEvent = new StripeCustomerActionBeforeRequestEvent(
            $stripeAction,
            'customersCreate',
            $createRequestArgs
        );

        $this->eventDispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->withConsecutive([$beforeSearchRequestEvent], [$beforeCreateRequestEvent]);

        $this->stripeClient->customers
            ->expects(self::once())
            ->method('create')
            ->with(...$beforeCreateRequestEvent->getRequestArgs())
            ->willReturn($stripeCustomer);

        $this->assertLoggerNotCalled();

        self::assertEquals(
            new StripeCustomerActionResult(successful: true, stripeCustomer: $stripeCustomer),
            $this->executor->executeAction($stripeAction)
        );
    }

    public function testExecuteActionCreatesNewWithBillingAddressWhenStripeCustomerNotFound(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 42);

        $stripeAction = new FindOrCreateStripeCustomerAction(
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $customerUser = (new CustomerUser())->setEmail('email@example.org');
        $billingAddress = (new OrderAddress())
            ->setCity('New York')
            ->setCountry(new Country('US'))
            ->setStreet('123 Main St')
            ->setStreet2('Apt. 45')
            ->setPostalCode(12345)
            ->setRegion((new Region('NY'))->setName('New York'));

        $paymentContext = new PaymentContext([
            PaymentContext::FIELD_CUSTOMER_USER => $customerUser,
            PaymentContext::FIELD_BILLING_ADDRESS => $billingAddress,
        ]);

        $this->transactionPaymentContextFactory
            ->expects(self::once())
            ->method('create')
            ->with($paymentTransaction)
            ->willReturn($paymentContext);

        $requestArgs = [['query' => sprintf("email:'%s'", addslashes($customerUser->getEmail()))]];

        $beforeSearchRequestEvent = new StripeCustomerActionBeforeRequestEvent(
            $stripeAction,
            'customersSearch',
            $requestArgs
        );

        $searchResult = new StripeSearchResult();
        $searchResult->data = [];

        $this->stripeClient->customers = $this->createMock(StripeCustomerService::class);
        $this->stripeClient->customers
            ->expects(self::once())
            ->method('search')
            ->with(...$beforeSearchRequestEvent->getRequestArgs())
            ->willReturn($searchResult);

        $customerUserName = 'Amanda Cole';
        $this->entityNameResolver
            ->expects(self::once())
            ->method('getName')
            ->with($customerUser)
            ->willReturn($customerUserName);

        $stripeCustomer = new StripeCustomer();

        $createRequestArgs = [
            [
                'email' => $customerUser->getEmail(),
                'name' => $customerUserName,
                'address' => [
                    'city' => $billingAddress->getCity(),
                    'country' => $billingAddress->getCountryIso2(),
                    'line1' => $billingAddress->getStreet(),
                    'line2' => $billingAddress->getStreet2(),
                    'postal_code' => $billingAddress->getPostalCode(),
                    'state' => $billingAddress->getRegionName(),
                ],
            ],
        ];

        $beforeCreateRequestEvent = new StripeCustomerActionBeforeRequestEvent(
            $stripeAction,
            'customersCreate',
            $createRequestArgs
        );

        $this->eventDispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->withConsecutive([$beforeSearchRequestEvent], [$beforeCreateRequestEvent]);

        $this->stripeClient->customers
            ->expects(self::once())
            ->method('create')
            ->with(...$beforeCreateRequestEvent->getRequestArgs())
            ->willReturn($stripeCustomer);

        $this->assertLoggerNotCalled();

        self::assertEquals(
            new StripeCustomerActionResult(successful: true, stripeCustomer: $stripeCustomer),
            $this->executor->executeAction($stripeAction)
        );
    }

    public function testExecuteActionCreatesNewWithPartialBillingAddressWhenStripeCustomerNotFound(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 42);

        $stripeAction = new FindOrCreateStripeCustomerAction(
            $this->stripePaymentElementConfig,
            $paymentTransaction
        );

        $customerUser = (new CustomerUser())->setEmail('email@example.org');
        $billingAddress = (new OrderAddress())
            ->setCity('New York')
            ->setCountry(new Country('US'))
            ->setStreet('123 Main St')
            ->setPostalCode(12345)
            ->setRegion((new Region('NY'))->setName('New York'));

        $paymentContext = new PaymentContext([
            PaymentContext::FIELD_CUSTOMER_USER => $customerUser,
            PaymentContext::FIELD_BILLING_ADDRESS => $billingAddress,
        ]);

        $this->transactionPaymentContextFactory
            ->expects(self::once())
            ->method('create')
            ->with($paymentTransaction)
            ->willReturn($paymentContext);

        $requestArgs = [['query' => sprintf("email:'%s'", addslashes($customerUser->getEmail()))]];

        $beforeSearchRequestEvent = new StripeCustomerActionBeforeRequestEvent(
            $stripeAction,
            'customersSearch',
            $requestArgs
        );

        $searchResult = new StripeSearchResult();
        $searchResult->data = [];

        $this->stripeClient->customers = $this->createMock(StripeCustomerService::class);
        $this->stripeClient->customers
            ->expects(self::once())
            ->method('search')
            ->with(...$beforeSearchRequestEvent->getRequestArgs())
            ->willReturn($searchResult);

        $customerUserName = 'Amanda Cole';
        $this->entityNameResolver
            ->expects(self::once())
            ->method('getName')
            ->with($customerUser)
            ->willReturn($customerUserName);

        $stripeCustomer = new StripeCustomer();

        $createRequestArgs = [
            [
                'email' => $customerUser->getEmail(),
                'name' => $customerUserName,
                'address' => [
                    'city' => $billingAddress->getCity(),
                    'country' => $billingAddress->getCountryIso2(),
                    'line1' => $billingAddress->getStreet(),
                    'postal_code' => $billingAddress->getPostalCode(),
                    'state' => $billingAddress->getRegionName(),
                ],
            ],
        ];

        $beforeCreateRequestEvent = new StripeCustomerActionBeforeRequestEvent(
            $stripeAction,
            'customersCreate',
            $createRequestArgs
        );

        $this->eventDispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->withConsecutive([$beforeSearchRequestEvent], [$beforeCreateRequestEvent]);

        $this->stripeClient->customers
            ->expects(self::once())
            ->method('create')
            ->with(...$beforeCreateRequestEvent->getRequestArgs())
            ->willReturn($stripeCustomer);

        $this->assertLoggerNotCalled();

        self::assertEquals(
            new StripeCustomerActionResult(successful: true, stripeCustomer: $stripeCustomer),
            $this->executor->executeAction($stripeAction)
        );
    }
}
