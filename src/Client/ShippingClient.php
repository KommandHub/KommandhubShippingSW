<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Client;

use Kommandhub\ShippingSW\Client\Http\HttpClientInterface;
use Kommandhub\ShippingSW\Client\Resource\Ping;

/**
 * Facade over the Shipping API resources.
 *
 * Services depend on this one class rather than on a dozen resource classes,
 * which keeps constructor signatures stable as the API surface grows. Add one
 * accessor per Client/Resource/ class.
 */
class ShippingClient
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function ping(): Ping
    {
        return new Ping($this->httpClient);
    }
}
