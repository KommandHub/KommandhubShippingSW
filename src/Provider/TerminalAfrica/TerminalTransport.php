<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\TerminalAfrica;

use Kommandhub\ShippingSW\Provider\Exception\ProviderException;
use Kommandhub\ShippingSW\Provider\ProviderContext;

/**
 * The HTTP seam for the Terminal Africa (TShip) API. Isolating it behind an
 * interface keeps every network concern out of the adapter's mapping logic and
 * lets the conformance/mapping tests run against canned fixtures with no I/O.
 */
interface TerminalTransport
{
    /**
     * Send a request and return the decoded JSON body.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     *
     * @throws ProviderException on transport failure or a non-2xx response
     */
    public function request(string $method, string $path, array $body, ProviderContext $context): array;
}
