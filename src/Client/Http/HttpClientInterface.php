<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Client\Http;

use Kommandhub\ShippingSW\Exception\ShippingException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Interface HttpClientInterface.
 */
interface HttpClientInterface
{
    /**
     * Send a GET request.
     *
     *
     * @throws ShippingException
     */
    public function get(string $endpoint, array $queryParams = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * Send a POST request.
     *
     *
     * @throws ShippingException
     */
    public function post(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * Send a PUT request.
     *
     *
     * @throws ShippingException
     */
    public function put(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * Send a DELETE request.
     *
     *
     * @throws ShippingException
     */
    public function delete(string $endpoint, ?string $salesChannelId = null): ResponseInterface;
}
