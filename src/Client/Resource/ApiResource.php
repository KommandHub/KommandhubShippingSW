<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Client\Resource;

use Kommandhub\ShippingSW\Client\Http\HttpClientInterface;
use Kommandhub\ShippingSW\Exception\ShippingException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Base class for one Shipping API endpoint group.
 *
 * One subclass per endpoint group, each method mapping to a single documented
 * call. Nothing outside Client/ should build a URL or a payload by hand.
 */
abstract class ApiResource
{
    public function __construct(protected HttpClientInterface $httpClient)
    {
    }

    /**
     * Decodes an API response into an array.
     *
     * `toArray()` throws on any non-2xx status by default, which discards the
     * error body — and most APIs put the actionable message *in* that body
     * (`{"status": false, "message": "..."}`). Passing `false` keeps the body,
     * so callers can branch on it and surface the real reason to the merchant
     * instead of an opaque HTTP exception.
     *
     * Transport failures and non-JSON bodies are real faults and surface as
     * ShippingException.
     *
     * @return array<string, mixed>
     *
     * @throws ShippingException
     */
    protected function response(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (\Throwable $exception) {
            throw new ShippingException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }
}
