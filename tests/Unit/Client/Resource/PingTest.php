<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Client\Resource;

use Kommandhub\ShippingSW\Client\Http\HttpClientInterface;
use Kommandhub\ShippingSW\Client\Resource\Ping;
use Kommandhub\ShippingSW\Exception\ShippingException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Ping::class)]
class PingTest extends TestCase
{
    public function testStatusReturnsDecodedBody(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->with(false)->willReturn(['status' => true]);

        $http = $this->createMock(HttpClientInterface::class);
        $http->expects(static::once())
            ->method('get')
            ->with('/status', [], 'sales-channel-id')
            ->willReturn($response);

        static::assertSame(['status' => true], (new Ping($http))->status('sales-channel-id'));
    }

    /**
     * An error body must survive decoding — see ApiResource::response().
     */
    public function testErrorBodyIsReturnedRatherThanThrown(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->with(false)->willReturn(['status' => false, 'message' => 'nope']);

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('get')->willReturn($response);

        static::assertSame(
            ['status' => false, 'message' => 'nope'],
            (new Ping($http))->status()
        );
    }

    public function testUndecodableResponseBecomesPluginException(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException(
            $this->createMock(TransportExceptionInterface::class)
        );

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('get')->willReturn($response);

        $this->expectException(ShippingException::class);

        (new Ping($http))->status();
    }
}
