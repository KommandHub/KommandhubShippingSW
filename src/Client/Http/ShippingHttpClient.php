<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Client\Http;

use Kommandhub\ShippingSW\Exception\ShippingException;
use Kommandhub\ShippingSW\Setting\Service\Config;
// CircuitBreaker is in this namespace (Kommandhub\ShippingSW\Client\Http).
use Symfony\Contracts\HttpClient\HttpClientInterface as SymfonyHttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Transport for the Shipping REST API.
 *
 * The only class in the plugin that knows the base URL, how credentials are
 * presented, and how a transport failure becomes a plugin exception. Endpoint
 * knowledge belongs in Client/Resource/*, not here.
 *
 * Credentials are resolved per request rather than per instance: they are
 * sales-channel scoped settings, and this service is shared across sales
 * channels within one request.
 */
class ShippingHttpClient implements HttpClientInterface
{
    private const BASE_URL_LIVE = 'https://api.example.com';

    private const BASE_URL_SANDBOX = 'https://sandbox-api.example.com';

    public function __construct(
        private readonly Config $config,
        private readonly SymfonyHttpClientInterface $client,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @throws ShippingException
     */
    public function get(string $endpoint, array $queryParams = [], ?string $salesChannelId = null): ResponseInterface
    {
        return $this->request('GET', $endpoint, ['query' => $queryParams], $salesChannelId);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ShippingException
     */
    public function post(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface
    {
        return $this->request('POST', $endpoint, ['json' => $payload], $salesChannelId);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ShippingException
     */
    public function put(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface
    {
        return $this->request('PUT', $endpoint, ['json' => $payload], $salesChannelId);
    }

    /**
     * @throws ShippingException
     */
    public function delete(string $endpoint, ?string $salesChannelId = null): ResponseInterface
    {
        return $this->request('DELETE', $endpoint, [], $salesChannelId);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws ShippingException
     */
    private function request(string $method, string $endpoint, array $options, ?string $salesChannelId): ResponseInterface
    {
        $baseUrl = $this->getBaseUrl($salesChannelId);
        $circuit = (string) parse_url($baseUrl, PHP_URL_HOST);

        // Fail fast when the host is already known to be down, so checkout can
        // drop to its flat-rate fallback instead of waiting on a dead endpoint.
        $this->circuitBreaker->guard($circuit);

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $this->getSecretKey($salesChannelId),
            'Content-Type' => 'application/json',
        ];
        $options['timeout'] ??= $this->timeoutSeconds;

        try {
            $response = $this->client->request($method, $baseUrl . $endpoint, $options);
            // Eager transport faults (DNS/TLS/connect) surface here; a 4xx/5xx
            // with a body does not — that is inspected in ApiResource::response().
            // ponytail: lazy timeouts on body read are recorded by the adapter
            // that consumes the response, not here.
            $this->circuitBreaker->recordSuccess($circuit);

            return $response;
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure($circuit);

            throw new ShippingException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    private function getBaseUrl(?string $salesChannelId): string
    {
        return $this->isSandbox($salesChannelId) ? self::BASE_URL_SANDBOX : self::BASE_URL_LIVE;
    }

    private function getSecretKey(?string $salesChannelId): string
    {
        return $this->isSandbox($salesChannelId)
            ? $this->config->getString('apiKeySandbox', $salesChannelId)
            : $this->config->getString('apiKey', $salesChannelId);
    }

    private function isSandbox(?string $salesChannelId): bool
    {
        return $this->config->getBool('enableSandbox', $salesChannelId);
    }
}
