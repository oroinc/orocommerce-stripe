<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\PaymentMethod\StripePaymentElement;

use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\StripePaymentElementMethod;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\StripePaymentElementMethodFactory;
use Oro\Bundle\StripePaymentBundle\StripeAmountValidator\StripeAmountValidatorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorInterface;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StripePaymentElementMethodFactoryTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private StripePaymentElementMethodFactory $factory;

    private MockObject&StripePaymentIntentActionExecutorInterface $stripePaymentActionExecutor;

    private MockObject&StripeAmountValidatorInterface $stripeAmountValidator;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    protected function setUp(): void
    {
        $this->stripePaymentActionExecutor = $this->createMock(StripePaymentIntentActionExecutorInterface::class);
        $this->stripeAmountValidator = $this->createMock(StripeAmountValidatorInterface::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->factory = new StripePaymentElementMethodFactory(
            $this->stripePaymentActionExecutor,
            $this->stripeAmountValidator,
            $this->paymentTransactionProvider
        );
        $this->setUpLoggerMock($this->factory);
    }

    public function testCreate(): void
    {
        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $paymentMethodGroups = ['sample_group1', 'sample_group2'];

        $expectedPaymentMethod = new StripePaymentElementMethod(
            $stripePaymentElementConfig,
            $this->stripePaymentActionExecutor,
            $this->stripeAmountValidator,
            $this->paymentTransactionProvider,
            $paymentMethodGroups
        );
        $expectedPaymentMethod->setLogger($this->loggerMock);

        $paymentMethod = $this->factory->create($stripePaymentElementConfig, $paymentMethodGroups);

        self::assertEquals($expectedPaymentMethod, $paymentMethod);
    }
}
