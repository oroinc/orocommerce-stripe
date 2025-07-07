<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeClient;

use Oro\Bundle\PaymentBundle\Entity\RequestLogsAwareInterface;
use Oro\Bundle\SecurityBundle\Tools\UUIDGenerator;
use Stripe\Exception\ApiErrorException as StripeApiErrorException;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decorates StripeClient with extra logging of all requests and responses made to Stripe API.
 */
class LoggingStripeClient extends StripeClient implements LoggingStripeClientInterface, ResetInterface
{
    /**
     * @var array<string,array{
     *  method: string,
     *  path: string,
     *  params: array<string,mixed>,
     *  timestamp: float,
     *  scope: string|null,
     * }>
     */
    private array $requestLogs = [];

    /**
     * @var array<array,array{
     *  response?: array<string,mixed>,
     *  error?: string,
     *  timestamp: float,
     *  scope: string|null
     * }>
     */
    private array $responseLogs = [];

    private ?string $scope = null;

    private bool $autoCloseScope = true;

    private ?RequestLogsAwareInterface $scopeEntity = null;

    public function __construct(array|string $config = [])
    {
        parent::__construct($config);
    }

    #[\Override]
    public function beginScope(?string $scope = null, bool $autoClose = true): string
    {
        $this->scope = $scope ?? UUIDGenerator::v4();
        $this->autoCloseScope = $autoClose;
        $this->scopeEntity = null;

        return $this->scope;
    }

    #[\Override]
    public function beginScopeFor(
        RequestLogsAwareInterface $scopeEntity,
        ?string $scope = null,
        bool $autoClose = true
    ): string {
        $scope = $this->beginScope($scope, $autoClose);
        $this->scopeEntity = $scopeEntity;

        return $scope;
    }

    #[\Override]
    public function endScope(): void
    {
        if ($this->scopeEntity !== null) {
            foreach ($this->getRequestLogs($this->scope) as $requestLog) {
                $this->scopeEntity->addRequestLog($requestLog);
            }

            foreach ($this->getResponseLogs($this->scope) as $responseLog) {
                $this->scopeEntity->addResponseLog($responseLog);
            }
        }

        $this->scope = null;
        $this->scopeEntity = null;
    }

    #[\Override]
    public function getRequestLogs(?string $scope = null): array
    {
        if ($scope === null) {
            return $this->requestLogs;
        }

        return array_filter(
            $this->requestLogs,
            static fn (array $log): bool => isset($log['scope']) && $log['scope'] === $scope
        );
    }

    #[\Override]
    public function getResponseLogs(?string $scope = null): array
    {
        if ($scope === null) {
            return $this->responseLogs;
        }

        return array_filter(
            $this->responseLogs,
            static fn (array $log): bool => isset($log['scope']) && $log['scope'] === $scope
        );
    }

    #[\Override]
    public function getAllLogs(?string $scope = null): array
    {
        return [
            'requests' => $this->getRequestLogs($scope),
            'responses' => $this->getResponseLogs($scope),
        ];
    }

    #[\Override]
    public function request($method, $path, $params, $opts): StripeObject
    {
        $uuid = UUIDGenerator::v4();
        $this->requestLogs[$uuid] = [
            'method' => $method,
            'path' => $path,
            'params' => $params,
            'timestamp' => microtime(true),
            'scope' => $this->scope,
        ];

        try {
            $response = $this->doRequest($method, $path, $params, $opts);

            $this->responseLogs[$uuid] = [
                'response' => $response->toArray(),
                'timestamp' => microtime(true),
                'scope' => $this->scope,
            ];

            return $response;
        } catch (StripeApiErrorException $apiErrorException) {
            $this->responseLogs[$uuid] = [
                'error' => $apiErrorException->getMessage(),
                'timestamp' => microtime(true),
                'scope' => $this->scope,
            ];

            throw $apiErrorException;
        } finally {
            if ($this->autoCloseScope) {
                $this->endScope();
            }
        }
    }

    protected function doRequest($method, $path, $params, $opts): StripeObject
    {
        return parent::request($method, $path, $params, $opts);
    }

    #[\Override]
    public function reset(): void
    {
        $this->scope = null;
        $this->scopeEntity = null;
        $this->requestLogs = [];
        $this->responseLogs = [];
    }
}
