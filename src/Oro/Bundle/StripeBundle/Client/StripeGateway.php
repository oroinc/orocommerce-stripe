<?php

namespace Oro\Bundle\StripeBundle\Client;

use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Client\Request\StripeApiRequestInterface;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\RefundResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

/**
 * Stripe gateway encapsulates logic for basic payment functionality.
 * Uses Stripe SDK to perform an interaction with Stripe payment service.
 */
class StripeGateway implements StripeGatewayInterface
{
    private ?StripeClient $client = null;
    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    private function getClient(): StripeClient
    {
        if (null === $this->client) {
            $this->client = new StripeClient($this->secretKey);
        }

        return $this->client;
    }

    public function purchase(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        try {
            $paymentIntent = $this->getClient()->paymentIntents->create($request->getRequestData());
        } catch (ApiErrorException $e) {
            $this->handleException($e);
        }

        return new PaymentIntentResponse($this->getResponseData($paymentIntent));
    }

    public function confirm(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        try {
            $paymentIntent = $this->getClient()->paymentIntents
                ->retrieve($request->getPaymentId(), $request->getRequestData());

            if ($paymentIntent) {
                $paymentIntent->confirm();
            } else {
                throw new \LogicException('Payment intent is not found');
            }
        } catch (ApiErrorException $exception) {
            $this->handleException($exception);
        }

        return new PaymentIntentResponse($this->getResponseData($paymentIntent));
    }

    public function capture(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        try {
            $paymentIntent = $this->getClient()->paymentIntents->capture(
                $request->getPaymentId(),
                $request->getRequestData()
            );
        } catch (ApiErrorException $exception) {
            $this->handleException($exception);
        }

        return new PaymentIntentResponse($this->getResponseData($paymentIntent));
    }

    public function cancel(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        try {
            $paymentIntent = $this->getClient()->paymentIntents->cancel(
                $request->getPaymentId(),
                $request->getRequestData()
            );
        } catch (ApiErrorException $exception) {
            $this->handleException($exception);
        }

        return new PaymentIntentResponse($this->getResponseData($paymentIntent));
    }

    public function createSetupIntent(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        try {
            $setupIntent = $this->getClient()->setupIntents->create($request->getRequestData());
        } catch (ApiErrorException $e) {
            $this->handleException($e);
        }

        return new SetupIntentResponse($this->getResponseData($setupIntent));
    }

    public function createCustomer(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        try {
            $email = $request->getRequestData()['email'] ?? null;
            $customer = null;
            if ($email) {
                $searchResult = $this->getClient()->customers->search(
                    ['query' => sprintf("email:'%s'", addslashes($email))]
                );
                $customer = $searchResult->getIterator()->current();
            }
            if (!$customer) {
                $customer = $this->getClient()->customers->create($request->getRequestData());
            }
        } catch (ApiErrorException $e) {
            $this->handleException($e);
        }

        return new CustomerResponse($this->getResponseData($customer));
    }

    public function findSetupIntentCustomer(string $setupIntentId): ResponseObjectInterface
    {
        try {
            $setupIntent = $this->getClient()->setupIntents->retrieve($setupIntentId);
            $customer = $this->getClient()->customers->retrieve($setupIntent->customer);
        } catch (ApiErrorException $e) {
            $this->handleException($e);
        }

        return new CustomerResponse($this->getResponseData($customer));
    }

    public function findSetupIntent(string $setupIntentId): ResponseObjectInterface
    {
        try {
            $setupIntent = $this->getClient()->setupIntents->retrieve($setupIntentId);
        } catch (ApiErrorException $e) {
            $this->handleException($e);
        }

        return new SetupIntentResponse($this->getResponseData($setupIntent));
    }

    public function refund(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        try {
            $refund = $this->getClient()->refunds->create($request->getRequestData());
        } catch (ApiErrorException $e) {
            $this->handleException($e);
        }

        return new RefundResponse($this->getResponseData($refund));
    }

    /**
     * @param ApiErrorException $exception
     * @throws StripeApiException
     */
    private function handleException(ApiErrorException $exception)
    {
        $stripeCode = null;
        $declineCode = null;
        if ($exception instanceof CardException) {
            $stripeCode = $exception->getStripeCode();
            $declineCode = $exception->getDeclineCode();
        }
        throw new StripeApiException($exception->getMessage(), $stripeCode, $declineCode);
    }

    /**
     * Convert response data to the objects of ResponseObjectInterface type.
     *
     * @param mixed $responseObject
     * @return array
     */
    private function getResponseData($responseObject): array
    {
        return $responseObject->toArray();
    }
}
