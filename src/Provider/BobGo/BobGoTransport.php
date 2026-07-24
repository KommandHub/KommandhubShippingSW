<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\BobGo;

use Kommandhub\ShippingSW\Provider\Exception\ProviderException;
use Kommandhub\ShippingSW\Provider\ProviderContext;

/**
 * HTTP seam for the Bob Go API. Same shape as the Terminal seam by design — the
 * two providers differ only in payloads and signature, not in how they are
 * wired — so the adapter/mapping tests run against fixtures with no network.
 */
interface BobGoTransport
{
    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     *
     * @throws ProviderException
     */
    public function request(string $method, string $path, array $body, ProviderContext $context): array;
}
