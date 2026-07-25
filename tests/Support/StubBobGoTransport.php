<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Support;

use Kommandhub\ShippingSW\Provider\BobGo\BobGoTransport;
use Kommandhub\ShippingSW\Provider\ProviderContext;

/**
 * Replays recorded Bob Go fixtures by endpoint — no network.
 */
final class StubBobGoTransport implements BobGoTransport
{
    /** @var list<array{method: string, path: string, body: array<string, mixed>}> */
    public array $calls = [];

    private static function fixture(string $name): array
    {
        $path = \dirname(__DIR__) . '/Fixtures/bobgo/' . $name . '.json';
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function request(string $method, string $path, array $body, ProviderContext $context): array
    {
        $this->calls[] = ['method' => $method, 'path' => $path, 'body' => $body];

        return match (true) {
            str_contains($path, '/providers') => self::fixture('providers_response'),
            str_contains($path, '/rates') => self::fixture('rates_response'),
            str_contains($path, '/tracking/') => self::fixture('track_response'),
            str_contains($path, '/shipments/') => self::fixture('label_response'),
            str_contains($path, '/shipments') => self::fixture('create_shipment_response'),
            default => throw new \RuntimeException('No stub fixture for Bob Go path: ' . $path),
        };
    }
}
