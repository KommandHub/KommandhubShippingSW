<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Support;

use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TerminalTransport;

/**
 * Replays recorded TShip fixtures by endpoint, so the adapter and conformance
 * suite run with zero network. Records the last request for assertion.
 */
final class StubTerminalTransport implements TerminalTransport
{
    /** @var list<array{method: string, path: string, body: array<string, mixed>}> */
    public array $calls = [];

    private static function fixture(string $name): array
    {
        $path = \dirname(__DIR__) . '/Fixtures/terminal/' . $name . '.json';
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function request(string $method, string $path, array $body, ProviderContext $context): array
    {
        $this->calls[] = ['method' => $method, 'path' => $path, 'body' => $body];

        return match (true) {
            str_contains($path, '/rates/') => self::fixture('rates_response'),
            str_contains($path, '/carriers') => self::fixture('carriers_response'),
            str_contains($path, '/shipments/pickup') => self::fixture('pickup_response'),
            str_contains($path, '/label') => self::fixture('label_response'),
            str_contains($path, '/track/') => self::fixture('track_response'),
            str_contains($path, '/shipments') => self::fixture('create_shipment_response'),
            default => throw new \RuntimeException('No stub fixture for path: ' . $path),
        };
    }
}
