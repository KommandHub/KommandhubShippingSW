<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider\BobGo;

use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Provider\BobGo\BobGoAdapter;
use Kommandhub\ShippingSW\Provider\BobGo\BobGoMapper;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Tests\Support\StubBobGoTransport;
use PHPUnit\Framework\TestCase;

final class BobGoWebhookTest extends TestCase
{
    private function adapter(): BobGoAdapter
    {
        return new BobGoAdapter(new StubBobGoTransport(), new BobGoMapper());
    }

    private function body(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3) . '/Fixtures/bobgo/webhook_event.json');
    }

    public function testValidSha256SignatureVerifies(): void
    {
        $context = new ProviderContext(apiKey: 'k', webhookSecret: 'whsec_bobgo');
        // Bob Go signs with SHA-256 — a different scheme from Terminal's SHA-512.
        $signature = hash_hmac('sha256', $this->body(), 'whsec_bobgo');

        self::assertTrue($this->adapter()->verifyWebhook($this->body(), $signature, $context));
    }

    public function testTerminalStyleSha512SignatureIsRejected(): void
    {
        $context = new ProviderContext(apiKey: 'k', webhookSecret: 'whsec_bobgo');
        $wrongScheme = hash_hmac('sha512', $this->body(), 'whsec_bobgo');

        self::assertFalse($this->adapter()->verifyWebhook($this->body(), $wrongScheme, $context));
    }

    public function testWebhookPayloadMapsToCanonicalEvent(): void
    {
        /** @var array{data: array<string, mixed>} $payload */
        $payload = json_decode($this->body(), true, 512, \JSON_THROW_ON_ERROR);
        $event = (new BobGoMapper())->toTrackingEvent($payload['data'], 'BG-REF-77');

        self::assertSame('bobgo', $event->providerKey);
        self::assertSame(TrackingStatus::DELIVERED, $event->status);
    }
}
