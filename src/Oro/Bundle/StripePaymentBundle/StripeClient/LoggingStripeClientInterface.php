<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeClient;

use Oro\Bundle\PaymentBundle\Entity\RequestLogsAwareInterface;

/**
 * Interface for StripeClient that provides logging capabilities for requests and responses.
 * This allows tracking API interactions with Stripe payment service for debugging and auditing purposes.
 */
interface LoggingStripeClientInterface
{
    /**
     * Begins a new logging scope.
     *
     * @param string|null $scope Optional scope identifier. If null, a unique scope will be generated.
     * @param bool $autoClose Whether to automatically close the scope after a request is made.
     *
     * @return string The identifier of the created scope.
     */
    public function beginScope(?string $scope = null, bool $autoClose = true): string;

    /**
     * Ends the current logging scope.
     */
    public function endScope(): void;

    /**
     * Begins a new logging scope associated with a specific entity. Automatically adds request and response log entries
     * to this entity after a request is made.
     *
     * @param RequestLogsAwareInterface $scopeEntity The entity to associate with the scope.
     * @param string|null $scope Optional scope identifier. If null, a unique scope will be generated.
     * @param bool $autoClose Whether to automatically close the scope after a request is made.
     *
     * @return string The identifier of the created scope.
     */
    public function beginScopeFor(
        RequestLogsAwareInterface $scopeEntity,
        ?string $scope = null,
        bool $autoClose = true
    ): string;

    /**
     * Gets all request logs for a specific scope.
     *
     * @param string|null $scope The scope identifier. If null, returns for all scopes.
     *
     * @return array<string,array{
     *   method: string,
     *   path: string,
     *   params: array<string,mixed>,
     *   timestamp: float,
     *   scope: string|null,
     *  }> The array of request logs.
     */
    public function getRequestLogs(?string $scope = null): array;

    /**
     * Gets all response logs for a specific scope.
     *
     * @param string|null $scope The scope identifier. If null, returns for all scopes.
     *
     * @return array<array,array{
     *   response?: array<string,mixed>,
     *   error?: string,
     *   timestamp: float,
     *   scope: string|null
     *  }> The array of response logs.
     */
    public function getResponseLogs(?string $scope = null): array;

    /**
     * Gets all logs (both requests and responses) for a specific scope.
     *
     * @param string|null $scope The scope identifier.If null, returns for all scopes.
     *
     * @return array{requests: array, responses: array} The combined array of request and response logs.
     */
    public function getAllLogs(?string $scope = null): array;
}
