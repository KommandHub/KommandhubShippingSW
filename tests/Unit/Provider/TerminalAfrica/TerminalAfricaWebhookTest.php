<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider\TerminalAfrica;

use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\Reference\InMemoryProviderReferenceStore;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TerminalAfricaAdapter;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TShipMapper;
use Kommandhub\ShippingSW\Tests\Support\StubTerminalTransport;
use PHPUnit\Framework\TestCase;

final class TerminalAfricaWebhookTest extends TestCase
{
    private function adapter(): TerminalAfricaAdapter
    {
        return new TerminalAfricaAdapter(new StubTerminalTransport(), new TShipMapper(), new InMemoryProviderReferenceStore());
    }

    private function body(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3) . '/Fixtures/terminal/webhook_event.json');
    }

    public function testValidSignatureVerifies(): void
    {
        $context = new ProviderContext(apiKey: 'k', webhookSecret: 'whsec_terminal');
        $signature = hash_hmac('sha512', $this->body(), 'whsec_terminal');

        self::assertTrue($this->adapter()->verifyWebhook($this->body(), $signature, $context));
    }

    public function testTamperedBodyFails(): void
    {
        $context = new ProviderContext(apiKey: 'k', webhookSecret: 'whsec_terminal');
        $signature = hash_hmac('sha512', $this->body(), 'whsec_terminal');

        self::assertFalse($this->adapter()->verifyWebhook($this->body() . ' ', $signature, $context));
    }

    public function testMissingSecretFailsClosed(): void
    {
        $context = new ProviderContext(apiKey: 'k', webhookSecret: null);
        $signature = hash_hmac('sha512', $this->body(), 'whsec_terminal');

        self::assertFalse($this->adapter()->verifyWebhook($this->body(), $signature, $context));
    }

    public function testWebhookPayloadMapsToCanonicalEvent(): void
    {
        /** @var array{data: array<string, mixed>} $payload */
        $payload = json_decode($this->body(), true, 512, \JSON_THROW_ON_ERROR);
        $event = (new TShipMapper())->toTrackingEvent($payload['data'], 'TA-TRK-001');

        self::assertSame('terminal_africa', $event->providerKey);
        self::assertSame('TA-TRK-001', $event->trackingNumber);
        self::assertSame(\Kommandhub\ShippingSW\Model\Tracking\TrackingStatus::DELIVERED, $event->status);
    }
}
