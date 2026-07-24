<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\BobGo;

use Kommandhub\ShippingSW\Client\Http\CircuitBreaker;
use Kommandhub\ShippingSW\Provider\Exception\ProviderException;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Live Bob Go transport over the resilient Symfony client with the shared
 * circuit breaker in front. Structurally identical to the Terminal transport —
 * only base URLs differ — which is itself evidence the infra is provider-neutral.
 */
final class HttpBobGoTransport implements BobGoTransport
{
    private const BASE_URL_LIVE = 'https://api.bobgo.co.za/v2';

    private const BASE_URL_SANDBOX = 'https://api.sandbox.bobgo.co.za/v2';

    private const CIRCUIT = BobGoMapper::PROVIDER_KEY;

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
            $this->logger->error('Bob Go transport failure', ['path' => $path, 'error' => $e->getMessage()]);

            throw new ProviderException('Bob Go request failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        if ($status >= 400) {
            $this->circuitBreaker->recordFailure(self::CIRCUIT);
            $message = \is_string($decoded['message'] ?? null) ? $decoded['message'] : 'HTTP ' . $status;

            throw new ProviderException(sprintf('Bob Go API error (%d): %s', $status, $message));
        }

        $this->circuitBreaker->recordSuccess(self::CIRCUIT);

        return $decoded;
    }
}
