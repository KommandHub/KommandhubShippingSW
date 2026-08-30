<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Carrier;

/**
 * Strongly-typed view over a carrier's raw provider payload. Business logic
 * depends on THIS, never on the raw JSON directly — that is the rule that keeps
 * provider-specific data maintainable. Each provider ships its own
 * implementation (TerminalCarrierData, BobGoCarrierData); unknown providers get
 * NullCarrierData.
 *
 * deliversTo() is the cross-provider method the core can rely on regardless of
 * how a given provider expresses coverage (or whether it does at all).
 */
interface CarrierData
{
    public function providerKey(): string;

    /**
     * @return array<string, mixed> the untouched provider payload (escape hatch;
     *                              prefer typed accessors on the concrete class)
     */
    public function raw(): array;

    /**
     * Whether this carrier can deliver to the given ISO-3166-1 alpha-2 country.
     * Providers without coverage data return true (no restriction), so a missing
     * capability never hides an otherwise valid option.
     */
    public function deliversTo(string $countryIso): bool;
}
