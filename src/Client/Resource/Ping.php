<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Client\Resource;

use Kommandhub\ShippingSW\Exception\ShippingException;

/**
 * Example endpoint group — replace with the real ones.
 *
 * Pattern to follow for every new resource:
 * - one public method per documented API call,
 * - the endpoint path is a literal here and nowhere else,
 * - the return type is the decoded array; interpreting it is the caller's job,
 * - `$salesChannelId` is threaded through so credentials resolve correctly.
 */
class Ping extends ApiResource
{
    /**
     * @return array<string, mixed>
     *
     * @throws ShippingException
     */
    public function status(?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->get('/status', [], $salesChannelId));
    }
}
