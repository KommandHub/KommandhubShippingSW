<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Support;

use Kommandhub\ShippingSW\Setting\Service\Config;

/**
 * Array-backed Config for unit tests — no SystemConfigService, no Shopware. The
 * parent constructor is intentionally bypassed; only the typed accessors are
 * overridden.
 */
final class FakeConfig extends Config
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private readonly array $values = [])
    {
    }

    public function get(string $key, array|bool|float|int|string|null $default = null, ?string $salesChannelId = null): array|bool|float|int|string|null
    {
        /** @var array|bool|float|int|string|null $value */
        $value = $this->values[$key] ?? $default;

        return $value;
    }

    public function getString(string $key, ?string $salesChannelId = null): string
    {
        $value = $this->values[$key] ?? '';

        return \is_string($value) ? $value : '';
    }

    public function getBool(string $key, ?string $salesChannelId = null): bool
    {
        return (bool) ($this->values[$key] ?? false);
    }

    /**
     * @return array<mixed>
     */
    public function getArray(string $key, ?string $salesChannelId = null): array
    {
        $value = $this->values[$key] ?? [];

        return \is_array($value) ? $value : [];
    }
}
