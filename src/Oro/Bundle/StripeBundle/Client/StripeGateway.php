<?php

namespace Oro\Bundle\StripeBundle\Client;

use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Client\Request\StripeApiRequestInterface;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
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
