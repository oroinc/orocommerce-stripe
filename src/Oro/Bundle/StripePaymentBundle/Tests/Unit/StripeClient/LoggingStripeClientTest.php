<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeClient;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\ApiErrorException as StripeApiErrorException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\StripeObject;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class LoggingStripeClientTest extends TestCase
{
    private LoggingStripeClient $client;

    private StripeObject $stripeResponse;

    private StripeApiErrorException $stripeException;

    protected function setUp(): void
    {
        $this->client = new class(['api_key' => 'test_key']) extends LoggingStripeClient {
            public ?StripeObject $mockResponse = null;
            public ?\Exception $mockException = null;

            #[\Override]
            protected function doRequest($method, $path, $params, $opts): StripeObject
            {
                if ($this->mockException) {
                    throw $this->mockException;
                }

                return $this->mockResponse ?? new StripeObject();
            }
        };

        $this->stripeResponse = new StripeObject(['id' => 'ch_123']);
        $this->stripeException = StripeInvalidRequestException::factory('Test error');
    }

    public function testBeginScopeGeneratesUniqueIds(): void
    {
        $scope1 = $this->client->beginScope();
        $scope2 = $this->client->beginScope();

        self::assertNotSame($scope1, $scope2);
    }

    public function testBeginScopeForGeneratesUniqueIds(): void
    {
        $scope1 = $this->client->beginScopeFor(new PaymentTransaction());
        $scope2 = $this->client->beginScopeFor(new PaymentTransaction());

        self::assertNotSame($scope1, $scope2);
    }

    public function testEndScopeClearsScopeFromBeginScope(): void
    {
        $scope = $this->client->beginScope();
        $this->client->endScope();

        self::assertNull($this->client->getRequestLogs()[$scope]['scope'] ?? null);
    }

    public function testEndScopeClearsScopeFromBeginScopeFor(): void
    {
        $scope = $this->client->beginScopeFor(new PaymentTransaction());
        $this->client->endScope();

        self::assertNull($this->client->getRequestLogs()[$scope]['scope'] ?? null);
    }

    public function testSuccessfulRequestLogging(): void
    {
        $this->client->mockResponse = $this->stripeResponse;

        $result = $this->client->request('POST', '/v1/charges', ['amount' => 1000], []);

        $logs = $this->client->getAllLogs();
        self::assertCount(1, $logs['requests']);
        self::assertCount(1, $logs['responses']);

        $requestLog = reset($logs['requests']);
        self::assertSame('POST', $requestLog['method']);
        self::assertSame('/v1/charges', $requestLog['path']);
        self::assertSame(['amount' => 1000], $requestLog['params']);

        $responseLog = reset($logs['responses']);
        self::assertEquals($this->stripeResponse->toArray(), $responseLog['response']);
        self::assertSame($this->stripeResponse, $result);
    }

    public function testSuccessfulRequestLoggingWhenBeginScopeForEntity(): void
    {
        $this->client->mockResponse = $this->stripeResponse;

        $paymentTransaction = new PaymentTransaction();
        $scope = $this->client->beginScopeFor($paymentTransaction);

        $result = $this->client->request('POST', '/v1/charges', ['amount' => 1000], []);

        $logs = $this->client->getAllLogs($scope);
        self::assertCount(1, $logs['requests']);
        self::assertCount(1, $logs['responses']);

        $requestLog = reset($logs['requests']);
        self::assertSame('POST', $requestLog['method']);
        self::assertSame('/v1/charges', $requestLog['path']);
        self::assertSame(['amount' => 1000], $requestLog['params']);

        $responseLog = reset($logs['responses']);
        self::assertEquals($this->stripeResponse->toArray(), $responseLog['response']);
        self::assertSame($this->stripeResponse, $result);

        self::assertEquals([$requestLog], $paymentTransaction->getRequestLogs());
        self::assertEquals([$responseLog], $paymentTransaction->getResponseLogs());
    }

    public function testSuccessfulRequestLoggingWhenBeginScopeForEntityWithoutAutoClose(): void
    {
        $this->client->mockResponse = $this->stripeResponse;

        $paymentTransaction = new PaymentTransaction();
        $scope = $this->client->beginScopeFor($paymentTransaction, null, false);

        $result1 = $this->client->request('POST', '/v1/charges', ['amount' => 1000], []);
        self::assertSame($this->stripeResponse, $result1);

        $result2 = $this->client->request('POST', '/v1/charges', ['amount' => 2000], []);
        self::assertSame($this->stripeResponse, $result2);

        $logs = $this->client->getAllLogs($scope);
        self::assertCount(2, $logs['requests']);
        self::assertCount(2, $logs['responses']);

        $requestLog = reset($logs['requests']);
        self::assertSame('POST', $requestLog['method']);
        self::assertSame('/v1/charges', $requestLog['path']);
        self::assertSame(['amount' => 1000], $requestLog['params']);

        $requestLog = next($logs['requests']);
        self::assertSame('POST', $requestLog['method']);
        self::assertSame('/v1/charges', $requestLog['path']);
        self::assertSame(['amount' => 2000], $requestLog['params']);

        $responseLog = reset($logs['responses']);
        self::assertEquals($this->stripeResponse->toArray(), $responseLog['response']);

        $responseLog = next($logs['responses']);
        self::assertEquals($this->stripeResponse->toArray(), $responseLog['response']);

        self::assertEquals([], $paymentTransaction->getRequestLogs());
        self::assertEquals([], $paymentTransaction->getResponseLogs());

        $this->client->endScope();

        self::assertEquals(array_values($logs['requests']), $paymentTransaction->getRequestLogs());
        self::assertEquals(array_values($logs['responses']), $paymentTransaction->getResponseLogs());
    }

    public function testFailedRequestLogging(): void
    {
        $this->client->mockException = $this->stripeException;

        try {
            $this->client->request('POST', '/v1/charges', ['amount' => 1000], []);
            self::fail('Expected exception was not thrown');
        } catch (StripeApiErrorException $exception) {
            $logs = $this->client->getAllLogs();
            self::assertCount(1, $logs['requests']);
            self::assertCount(1, $logs['responses']);

            $requestLog = reset($logs['requests']);
            self::assertSame('POST', $requestLog['method']);
            self::assertSame('/v1/charges', $requestLog['path']);
            self::assertSame(['amount' => 1000], $requestLog['params']);

            $responseLog = reset($logs['responses']);
            self::assertEquals($this->stripeException->getMessage(), $responseLog['error']);
        }
    }

    public function testFailedRequestLoggingWhenBeginScopeForEntity(): void
    {
        $this->client->mockException = $this->stripeException;

        $paymentTransaction = new PaymentTransaction();
        $scope = $this->client->beginScopeFor($paymentTransaction);

        try {
            $this->client->request('POST', '/v1/charges', ['amount' => 1000], []);
            self::fail('Expected exception was not thrown');
        } catch (StripeApiErrorException $exception) {
            $logs = $this->client->getAllLogs($scope);
            self::assertCount(1, $logs['requests']);
            self::assertCount(1, $logs['responses']);

            $requestLog = reset($logs['requests']);
            self::assertSame('POST', $requestLog['method']);
            self::assertSame('/v1/charges', $requestLog['path']);
            self::assertSame(['amount' => 1000], $requestLog['params']);

            $responseLog = reset($logs['responses']);
            self::assertEquals($this->stripeException->getMessage(), $responseLog['error']);

            self::assertEquals([$requestLog], $paymentTransaction->getRequestLogs());
            self::assertEquals([$responseLog], $paymentTransaction->getResponseLogs());
        }
    }

    public function testFailedRequestLoggingWhenBeginScopeForEntityWithoutAuthClose(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $scope = $this->client->beginScopeFor($paymentTransaction, null, false);

        try {
            $this->client->mockResponse = $this->stripeResponse;
            $this->client->request('POST', '/v1/charges', ['amount' => 1000], []);

            $this->client->mockException = $this->stripeException;
            $this->client->request('POST', '/v1/charges', ['amount' => 2000], []);

            self::fail('Expected exception was not thrown');
        } catch (StripeApiErrorException $exception) {
            $logs = $this->client->getAllLogs($scope);
            self::assertCount(2, $logs['requests']);
            self::assertCount(2, $logs['responses']);

            $requestLog = reset($logs['requests']);
            self::assertSame('POST', $requestLog['method']);
            self::assertSame('/v1/charges', $requestLog['path']);
            self::assertSame(['amount' => 1000], $requestLog['params']);

            $requestLog = next($logs['requests']);
            self::assertSame('POST', $requestLog['method']);
            self::assertSame('/v1/charges', $requestLog['path']);
            self::assertSame(['amount' => 2000], $requestLog['params']);

            $responseLog = reset($logs['responses']);
            self::assertEquals($this->stripeResponse->toArray(), $responseLog['response']);

            $responseLog = next($logs['responses']);
            self::assertEquals($this->stripeException->getMessage(), $responseLog['error']);

            self::assertEquals([], $paymentTransaction->getRequestLogs());
            self::assertEquals([], $paymentTransaction->getResponseLogs());

            $this->client->endScope();

            self::assertEquals(array_values($logs['requests']), $paymentTransaction->getRequestLogs());
            self::assertEquals(array_values($logs['responses']), $paymentTransaction->getResponseLogs());
        }
    }

    public function testScopeFiltering(): void
    {
        $scope = $this->client->beginScope();
        $this->client->request('GET', '/v1/balance', [], []);
        $this->client->endScope();

        $this->client->request('POST', '/v1/refunds', [], []);

        $scopedLogs = $this->client->getAllLogs($scope);
        $allLogs = $this->client->getAllLogs();

        self::assertCount(1, $scopedLogs['requests']);
        self::assertCount(2, $allLogs['requests']);
    }

    public function testResetFunctionality(): void
    {
        $this->client->beginScope();
        $this->client->request('GET', '/v1/balance', [], []);
        $this->client->reset();

        self::assertEmpty($this->client->getRequestLogs());
        self::assertEmpty($this->client->getResponseLogs());
        self::assertEquals(
            [
                'requests' => [],
                'responses' => [],
            ],
            $this->client->getAllLogs()
        );
        self::assertNull($this->client->getRequestLogs()[0]['scope'] ?? null);
    }

    public function testUniqueLogIdentifiers(): void
    {
        $this->client->request('GET', '/v1/balance', [], []);
        $this->client->request('POST', '/v1/charges', [], []);

        $logs = $this->client->getAllLogs();
        $requestIds = array_keys($logs['requests']);
        $responseIds = array_keys($logs['responses']);

        self::assertCount(2, array_unique($requestIds));
        self::assertEquals($requestIds, $responseIds);
    }

    public function testLogStructure(): void
    {
        $this->client->request('POST', '/v1/customers', ['email' => 'test@example.com'], []);

        $logEntry = current($this->client->getAllLogs()['requests']);
        self::assertArrayHasKey('method', $logEntry);
        self::assertArrayHasKey('path', $logEntry);
        self::assertArrayHasKey('params', $logEntry);
        self::assertArrayHasKey('timestamp', $logEntry);
    }

    public function testMultipleScopes(): void
    {
        $scope1 = $this->client->beginScope();
        $this->client->request('GET', '/v1/balance', [], []);
        $this->client->endScope();

        $scope2 = $this->client->beginScope();
        $this->client->request('POST', '/v1/charges', [], []);

        $scope1Logs = $this->client->getAllLogs($scope1);
        $scope2Logs = $this->client->getAllLogs($scope2);

        self::assertCount(1, $scope1Logs['requests']);
        self::assertCount(1, $scope2Logs['requests']);
    }
}
