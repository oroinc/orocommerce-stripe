<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\ApiFrontend\RestJsonApi;

use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Tests\Functional\ApiFrontend\DataFixtures\LoadCheckoutData;
use Oro\Bundle\CustomerBundle\Tests\Functional\ApiFrontend\DataFixtures\LoadAdminCustomerUserData;
use Oro\Bundle\FrontendBundle\Tests\Functional\ApiFrontend\FrontendRestJsonApiTestCase;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Tests\Functional\DataFixtures\LoadStripePaymentMethodData;
use Oro\Bundle\StripeBundle\Tests\Functional\Environment\Client\StripeGatewayMock;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class StripePaymentTest extends FrontendRestJsonApiTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures([
            LoadAdminCustomerUserData::class,
            LoadCheckoutData::class,
            LoadStripePaymentMethodData::class
        ]);
    }

    private function getPaymentMethod(int $checkoutId, string $type = 'stripe'): string
    {
        $response = $this->getSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'availablePaymentMethods']
        );
        $responseData = self::jsonToArray($response->getContent());
        foreach ($responseData['data'] as $paymentMethod) {
            if (str_starts_with($paymentMethod['id'], $type)) {
                return $paymentMethod['id'];
            }
        }
        throw new \RuntimeException(sprintf('The "%s" payment method was not found.', $type));
    }

    private function getShippingMethod(int $checkoutId): array
    {
        $response = $this->getSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'availableShippingMethods']
        );
        $responseData = self::jsonToArray($response->getContent());
        $shippingMethodData = $responseData['data'][0];
        $shippingMethodTypeData = reset($shippingMethodData['attributes']['types']);

        return [$shippingMethodData['id'], $shippingMethodTypeData['id']];
    }

    private function prepareCheckoutForPayment(
        int $checkoutId,
        string $paymentType = 'stripe',
        ?string $paymentMethod = null,
        ?string $shippingMethod = null,
        ?string $shippingMethodType = null
    ): void {
        if (null === $paymentMethod) {
            $paymentMethod = $this->getPaymentMethod($checkoutId, $paymentType);
        }
        if (null === $shippingMethod) {
            [$shippingMethod, $shippingMethodType] = $this->getShippingMethod($checkoutId);
        }
        $this->patch(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId],
            [
                'data' => [
                    'type' => 'checkouts',
                    'id' => (string)$checkoutId,
                    'attributes' => [
                        'paymentMethod' => $paymentMethod,
                        'shippingMethod' => $shippingMethod,
                        'shippingMethodType' => $shippingMethodType
                    ]
                ]
            ]
        );
    }

    private function sendInitialPaymentRequest(int $checkoutId, string $paymentId): void
    {
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentInfoStripe'],
            [
                'meta' => [
                    'stripePaymentMethodId' => $paymentId
                ]
            ]
        );
        $this->assertResponseContains(
            [
                'data' => [
                    'type' => 'checkouts',
                    'id' => (string)$checkoutId
                ]
            ],
            $response
        );
    }

    private function setPaymentTransactionStatus(int $checkoutId, string $action): void
    {
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        /** @var PaymentTransactionProvider $paymentTransactionProvider */
        $paymentTransactionProvider = self::getContainer()->get('oro_payment.provider.payment_transaction');
        $paymentTransaction = $paymentTransactionProvider->getPaymentTransaction($checkout->getOrder());
        $paymentTransaction->setAction($action);
        $paymentTransaction->setSuccessful(true);
        $paymentTransaction->setActive(true);
        $paymentTransaction->setReference('test');

        $paymentTransactionProvider->savePaymentTransaction($paymentTransaction);
    }

    public function testTryToPayPalExpressPaymentWhenSuccessUrlIsNotProvided(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'not blank constraint',
                'detail' => 'This value should not be blank.',
                'source' => ['pointer' => '/meta/successUrl']
            ],
            $response
        );
    }

    public function testTryToPayPalExpressPaymentWhenFailureUrlIsNotProvided(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'not blank constraint',
                'detail' => 'This value should not be blank.',
                'source' => ['pointer' => '/meta/failureUrl']
            ],
            $response
        );
    }

    public function testTryToPayPalExpressPaymentWhenPartiallyPaidUrlIsNotProvided(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'not blank constraint',
                'detail' => 'This value should not be blank.',
                'source' => ['pointer' => '/meta/partiallyPaidUrl']
            ],
            $response
        );
    }

    public function testTryToPayPalExpressPaymentWhenEmptyPaymentRequest(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [],
            [],
            false
        );
        $this->assertResponseValidationErrors(
            [
                [
                    'title' => 'not blank constraint',
                    'detail' => 'This value should not be blank.',
                    'source' => ['pointer' => '/meta/successUrl']
                ],
                [
                    'title' => 'not blank constraint',
                    'detail' => 'This value should not be blank.',
                    'source' => ['pointer' => '/meta/failureUrl']
                ],
                [
                    'title' => 'not blank constraint',
                    'detail' => 'This value should not be blank.',
                    'source' => ['pointer' => '/meta/partiallyPaidUrl']
                ]
            ],
            $response
        );
    }

    public function testTryToStripePaymentWithNullValuesForRequiredParameters(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => null,
                    'failureUrl' => null,
                    'partiallyPaidUrl' => null
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationErrors(
            [
                [
                    'title' => 'not blank constraint',
                    'detail' => 'This value should not be blank.',
                    'source' => ['pointer' => '/meta/successUrl']
                ],
                [
                    'title' => 'not blank constraint',
                    'detail' => 'This value should not be blank.',
                    'source' => ['pointer' => '/meta/failureUrl']
                ],
                [
                    'title' => 'not blank constraint',
                    'detail' => 'This value should not be blank.',
                    'source' => ['pointer' => '/meta/partiallyPaidUrl']
                ]
            ],
            $response
        );
    }

    public function testStripeInitialPaymentRequestWithEmptyRequest(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentInfoStripe'],
            [],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'not blank constraint',
                'detail' => 'This value should not be blank.',
                'source' => ['pointer' => '/meta/stripePaymentMethodId']
            ],
            $response
        );
    }

    public function testStripeInitialPaymentRequestWithNullValuesForRequiredParameters(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentInfoStripe'],
            [
                'meta' => [
                    'stripePaymentMethodId' => null
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'not blank constraint',
                'detail' => 'This value should not be blank.',
                'source' => ['pointer' => '/meta/stripePaymentMethodId']
            ],
            $response
        );
    }

    public function testTryToStripePaymentForNotReadyToPaymentCheckout(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'payment constraint',
                'detail' => 'The checkout is not ready for payment.',
                'meta' => [
                    'validatePaymentUrl' => $this->getUrl(
                        'oro_frontend_rest_api_subresource',
                        ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'payment'],
                        true
                    )
                ]
            ],
            $response
        );
    }

    public function testTryToStripePaymentForNotSupportedPaymentMethod(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId, 'payment_term');
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'access denied exception',
                'detail' => 'The payment method is not supported.'
            ],
            $response,
            Response::HTTP_FORBIDDEN
        );
    }

    public function testStripePaymentWhenPaymentError(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $this->sendInitialPaymentRequest($checkoutId, StripeGatewayMock::ERROR_CARD);

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'payment constraint',
                'detail' => 'Payment failed, please try again or select a different payment method.'
            ],
            $response
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertFalse($checkout->isPaymentInProgress());
        self::assertNull($checkout->getOrder());
    }

    public function testTryToStripePaymentWhenPaymentIdNotSet(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'runtime exception',
                'detail' => 'Stripe payment method id not provided.'
            ],
            $response,
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertFalse($checkout->isPaymentInProgress());
        self::assertNull($checkout->getOrder());
    }

    public function testStripePaymentWhenPaymentSuccessful(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $this->sendInitialPaymentRequest($checkoutId, StripeGatewayMock::NO_AUTH_CARD);

        $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ]
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertFalse($checkout->isPaymentInProgress());
        self::assertInstanceOf(Order::class, $checkout->getOrder());
    }

    public function testTryToStripePaymentWithAdditionalActionNotAllowedToChangeInProgressPayment(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $this->sendInitialPaymentRequest($checkoutId, StripeGatewayMock::AUTH_CARD);

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'payment action constraint',
                'detail' => 'The payment requires additional actions.',
                'meta' => [
                    'data' => [
                        'successful' => false,
                        'requires_action' => true
                    ]
                ]
            ],
            $response
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertTrue($checkout->isPaymentInProgress());
        self::assertInstanceOf(Order::class, $checkout->getOrder());

        // check that it's not possible to alter payment in progress process
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'payment status constraint',
                'detail' => 'Payment is being processed. '
                    . 'Please follow the payment provider\'s instructions to complete.'
            ],
            $response
        );
    }

    public function testStripePaymentWithAdditionalActionSuccessfulPaid(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $paymentMethod = $this->getPaymentMethod($checkoutId);
        [$shippingMethod, $shippingMethodType] = $this->getShippingMethod($checkoutId);
        $this->prepareCheckoutForPayment(
            $checkoutId,
            'stripe',
            $paymentMethod,
            $shippingMethod,
            $shippingMethodType
        );
        $this->sendInitialPaymentRequest($checkoutId, StripeGatewayMock::AUTH_CARD);

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'payment action constraint',
                'detail' => 'The payment requires additional actions.',
                'meta' => [
                    'data' => [
                        'successful' => false,
                        'requires_action' => true
                    ]
                ]
            ],
            $response
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertTrue($checkout->isPaymentInProgress());
        self::assertInstanceOf(Order::class, $checkout->getOrder());

        $this->setPaymentTransactionStatus($checkoutId, PaymentMethodInterface::AUTHORIZE);

        // make API payment request after success payment to finish checkout process
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ]
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertFalse($checkout->isPaymentInProgress());
        self::assertInstanceOf(Order::class, $checkout->getOrder());
        $responseData = self::jsonToArray($response->getContent());
        $this->assertResponseContains('order_for_ready_for_completion_checkout.yml', $response);
        self::assertEquals($paymentMethod, $responseData['data']['attributes']['paymentMethod'][0]['code']);
        self::assertEquals($shippingMethod, $responseData['data']['attributes']['shippingMethod']['code']);
        self::assertEquals($shippingMethodType, $responseData['data']['attributes']['shippingMethod']['type']);
        self::assertNotEmpty($responseData['data']['relationships']['billingAddress']['data']);
        self::assertNotEmpty($responseData['data']['relationships']['shippingAddress']['data']);
        self::assertCount(1, $responseData['data']['relationships']['lineItems']['data']);
    }

    public function testStripePaymentWithAdditionalActionCancelled(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $this->sendInitialPaymentRequest($checkoutId, StripeGatewayMock::AUTH_CARD);

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'payment action constraint',
                'detail' => 'The payment requires additional actions.',
                'meta' => [
                    'data' => [
                        'successful' => false,
                        'requires_action' => true
                    ]
                ]
            ],
            $response
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertTrue($checkout->isPaymentInProgress());
        self::assertInstanceOf(Order::class, $checkout->getOrder());

        $this->setPaymentTransactionStatus($checkoutId, PaymentMethodInterface::CANCEL);

        // make API payment request after success payment to finish checkout process
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripe'],
            [
                'meta' => [
                    'successUrl' => 'http://example.com/success',
                    'failureUrl' => 'http://example.com/failure',
                    'partiallyPaidUrl' => 'http://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'payment constraint',
                'detail' => 'Payment failed, please try again or select a different payment method.'
            ],
            $response
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertFalse($checkout->isPaymentInProgress());
        self::assertNull($checkout->getOrder());
    }
}
