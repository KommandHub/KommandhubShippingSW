<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\TerminalAfrica;

use Kommandhub\ShippingSW\Client\Http\CircuitBreaker;
use Kommandhub\ShippingSW\Provider\Exception\ProviderException;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Live TShip transport over the resilient Symfony client (retry + timeout) with
 * the shared circuit breaker in front, so a Terminal outage fails fast into the
 * checkout flat-rate fallback instead of hanging.
 */
final class HttpTerminalTransport implements TerminalTransport
{
    private const BASE_URL_LIVE = 'https://api.terminal.africa/v1';

    private const BASE_URL_SANDBOX = 'https://sandbox.terminal.africa/v1';

    private const CIRCUIT = TShipMapper::PROVIDER_KEY;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly LoggerInterface $logger,
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public function request(string $method, string $path, array $body, ProviderContext $context): array
    {
        $this->circuitBreaker->guard(self::CIRCUIT);

        $baseUrl = $context->sandbox ? self::BASE_URL_SANDBOX : self::BASE_URL_LIVE;
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $context->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => $this->timeoutSeconds,
        ];
        if ([] !== $body) {
            $options['json'] = $body;
        }

        try {
            $response = $this->client->request($method, $baseUrl . $path, $options);
            $status = $response->getStatusCode();
            $decoded = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure(self::CIRCUIT);
            $this->logger->error('Terminal transport failure', ['path' => $path, 'error' => $e->getMessage()]);

            throw new ProviderException('Terminal request failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        if ($status >= 400) {
            $this->circuitBreaker->recordFailure(self::CIRCUIT);
            $message = \is_string($decoded['message'] ?? null) ? $decoded['message'] : 'HTTP ' . $status;
            $this->logger->warning('Terminal API error', ['path' => $path, 'status' => $status, 'message' => $message]);

            throw new ProviderException(sprintf('Terminal API error (%d): %s', $status, $message));
        }

        $this->circuitBreaker->recordSuccess(self::CIRCUIT);

        return $decoded;
    }
}
