<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\ApiFrontend\RestJsonApi;

use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Tests\Functional\ApiFrontend\DataFixtures\LoadCheckoutData;
use Oro\Bundle\CustomerBundle\Tests\Functional\ApiFrontend\DataFixtures\LoadAdminCustomerUserData;
use Oro\Bundle\FrontendBundle\Tests\Functional\ApiFrontend\FrontendRestJsonApiTestCase;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\PaymentStatus\PaymentStatuses;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures\LoadStripePaymentElementMethodData;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\Environment\Client\StripePaymentElementTestData;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class StripePaymentElementTest extends FrontendRestJsonApiTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures([
            LoadAdminCustomerUserData::class,
            LoadCheckoutData::class,
            LoadStripePaymentElementMethodData::class
        ]);
    }

    private function getPaymentMethod(int $checkoutId, string $type = 'stripe_payment_element'): string
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
        string $paymentType = 'stripe_payment_element',
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

    private function sendInitialPaymentRequest(int $checkoutId, string $confirmationTokenId): void
    {
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentInfoStripePaymentElement'],
            [
                'meta' => [
                    'confirmationTokenId' => $confirmationTokenId,
                    'paymentMethodType' => 'card'
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

    public function testTryToPaymentWhenSuccessUrlIsNotProvided(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
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

    public function testTryToPaymentWhenFailureUrlIsNotProvided(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
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

    public function testTryToPaymentWhenPartiallyPaidUrlIsNotProvided(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure'
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

    public function testTryToPaymentWhenEmptyPaymentRequest(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
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

    public function testTryToPaymentWithNullValuesForRequiredParameters(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
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

    public function testInitialPaymentRequestWithEmptyRequest(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentInfoStripePaymentElement'],
            [],
            [],
            false
        );
        $this->assertResponseValidationErrors(
            [
                [
                    'title' => 'not blank constraint',
                    'detail' => 'This value should not be blank.',
                    'source' => ['pointer' => '/meta/confirmationTokenId']
                ],
                [
                    'title' => 'not blank constraint',
                    'detail' => 'This value should not be blank.',
                    'source' => ['pointer' => '/meta/paymentMethodType']
                ]
            ],
            $response
        );
    }

    public function testInitialPaymentRequestWithNullValuesForRequiredParameters(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentInfoStripePaymentElement'],
            [
                'meta' => [
                    'confirmationTokenId' => null,
                    'paymentMethodType' => null
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
                    'source' => ['pointer' => '/meta/confirmationTokenId']
                ],
                [
                    'title' => 'not blank constraint',
                    'detail' => 'This value should not be blank.',
                    'source' => ['pointer' => '/meta/paymentMethodType']
                ]
            ],
            $response
        );
    }

    public function testTryToPaymentForNotReadyToPaymentCheckout(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
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

    public function testTryToPaymentForNotSupportedPaymentMethod(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId, 'payment_term');
        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
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

    public function testPaymentWhenPaymentError(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $this->sendInitialPaymentRequest($checkoutId, StripePaymentElementTestData::ERROR_TOKEN);
        StripePaymentElementTestData::mockFindCustomer();
        StripePaymentElementTestData::mockDeclinedPayment();

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'payment constraint',
                'detail' => 'Your card was declined. Error Code: "card_declined", Decline Code: "generic_decline"'
            ],
            $response
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertFalse($checkout->isPaymentInProgress());
        self::assertNull($checkout->getOrder());
    }

    public function testTryToPaymentWhenConfirmationTokenNotSet(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
                ]
            ],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title' => 'runtime exception',
                'detail' => 'Stripe confirmation token not provided'
            ],
            $response,
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertFalse($checkout->isPaymentInProgress());
        self::assertNull($checkout->getOrder());
    }

    public function testPaymentWhenPaymentSuccessful(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $paymentMethod = $this->getPaymentMethod($checkoutId);
        [$shippingMethod, $shippingMethodType] = $this->getShippingMethod($checkoutId);
        $this->prepareCheckoutForPayment(
            $checkoutId,
            'stripe_payment_element',
            $paymentMethod,
            $shippingMethod,
            $shippingMethodType
        );
        $this->sendInitialPaymentRequest($checkoutId, StripePaymentElementTestData::NO_AUTH_TOKEN);
        StripePaymentElementTestData::mockFindCustomer();
        StripePaymentElementTestData::mockSuccessfulPayment();

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
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
        self::assertCount(2, $responseData['data']['relationships']['lineItems']['data']);
    }

    public function testTryToPaymentWithAdditionalActionNotAllowedToChangeInProgressPayment(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $this->sendInitialPaymentRequest($checkoutId, StripePaymentElementTestData::AUTH_TOKEN);
        StripePaymentElementTestData::mockFindCustomer();
        StripePaymentElementTestData::mockPaymentRequiresAction();

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
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
                        'requiresAction' => true
                    ]
                ]
            ],
            $response
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertTrue($checkout->isPaymentInProgress());
        self::assertInstanceOf(Order::class, $checkout->getOrder());

        // Try to create a new payment while payment is in progress
        $this->sendInitialPaymentRequest($checkoutId, StripePaymentElementTestData::NO_AUTH_TOKEN);

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
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

    public function testPaymentWithAdditionalActionSuccessfulPaid(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $paymentMethod = $this->getPaymentMethod($checkoutId);
        [$shippingMethod, $shippingMethodType] = $this->getShippingMethod($checkoutId);
        $this->prepareCheckoutForPayment(
            $checkoutId,
            'stripe_payment_element',
            $paymentMethod,
            $shippingMethod,
            $shippingMethodType
        );
        $this->sendInitialPaymentRequest($checkoutId, StripePaymentElementTestData::AUTH_TOKEN);
        StripePaymentElementTestData::mockFindCustomer();
        StripePaymentElementTestData::mockPaymentRequiresAction();

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
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
                        'requiresAction' => true
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
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
                ]
            ]
        );
        /** @var Checkout $checkout */
        $checkout = $this->getEntityManager()->find(Checkout::class, $checkoutId);
        self::assertFalse($checkout->isPaymentInProgress());
        self::assertInstanceOf(Order::class, $checkout->getOrder());
        $responseData = self::jsonToArray($response->getContent());

        $expectedContent = $this->getResponseData('order_for_ready_for_completion_checkout.yml');
        $expectedContent['data']['attributes']['paymentStatus'] = [
            'code' => PaymentStatuses::AUTHORIZED,
            'label' => 'Payment authorized'
        ];
        $this->assertResponseContains($expectedContent, $response);

        self::assertEquals($paymentMethod, $responseData['data']['attributes']['paymentMethod'][0]['code']);
        self::assertEquals($shippingMethod, $responseData['data']['attributes']['shippingMethod']['code']);
        self::assertEquals($shippingMethodType, $responseData['data']['attributes']['shippingMethod']['type']);
        self::assertNotEmpty($responseData['data']['relationships']['billingAddress']['data']);
        self::assertNotEmpty($responseData['data']['relationships']['shippingAddress']['data']);
        self::assertCount(2, $responseData['data']['relationships']['lineItems']['data']);
    }

    public function testPaymentWithAdditionalActionCancelled(): void
    {
        $checkoutId = $this->getReference('checkout.ready_for_completion')->getId();
        $this->prepareCheckoutForPayment($checkoutId);
        $this->sendInitialPaymentRequest($checkoutId, StripePaymentElementTestData::AUTH_TOKEN);
        StripePaymentElementTestData::mockFindCustomer();
        StripePaymentElementTestData::mockPaymentRequiresAction();

        $response = $this->postSubresource(
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
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
                        'requiresAction' => true
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
            ['entity' => 'checkouts', 'id' => (string)$checkoutId, 'association' => 'paymentStripePaymentElement'],
            [
                'meta' => [
                    'successUrl' => 'https://example.com/success',
                    'failureUrl' => 'https://example.com/failure',
                    'partiallyPaidUrl' => 'https://example.com/partiallyPaid'
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
