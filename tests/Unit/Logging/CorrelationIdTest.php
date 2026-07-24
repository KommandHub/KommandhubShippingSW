<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Logging;

use Kommandhub\ShippingSW\Logging\CorrelationId;
use PHPUnit\Framework\TestCase;

final class CorrelationIdTest extends TestCase
{
    public function testGeneratesAStableIdWithinTheCycle(): void
    {
        $correlationId = new CorrelationId();

        $first = $correlationId->get();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
        self::assertSame($first, $correlationId->get(), 'The id must be stable once generated.');
    }

    public function testInboundIdOverridesGeneration(): void
    {
        $correlationId = new CorrelationId();
        $correlationId->set('inbound-webhook-123');

        self::assertSame('inbound-webhook-123', $correlationId->get());
    }
}
