<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Rate;

/**
 * An immutable, iterable set of RateQuote. Sorting/filtering return new
 * instances so a cached collection can never be mutated by a caller.
 *
 * @implements \IteratorAggregate<int, RateQuote>
 */
final readonly class RateQuoteCollection implements \IteratorAggregate, \Countable
{
    /** @var list<RateQuote> */
    private array $quotes;

    public function __construct(RateQuote ...$quotes)
    {
        $this->quotes = array_values($quotes);
    }

    /** @return list<RateQuote> */
    public function all(): array
    {
        return $this->quotes;
    }

    public function isEmpty(): bool
    {
        return [] === $this->quotes;
    }

    public function count(): int
    {
        return \count($this->quotes);
    }

    /** New collection ordered cheapest first. */
    public function sortedByPrice(): self
    {
        $sorted = $this->quotes;
        usort($sorted, static fn (RateQuote $a, RateQuote $b): int => $a->amount->minorAmount <=> $b->amount->minorAmount);

        return new self(...$sorted);
    }

    public function cheapest(): ?RateQuote
    {
        if ($this->isEmpty()) {
            return null;
        }

        return $this->sortedByPrice()->all()[0];
    }

    /** The quote for a given carrier code, if present. */
    public function firstWithCarrierCode(string $carrierCode): ?RateQuote
    {
        foreach ($this->quotes as $quote) {
            if ($quote->carrierCode === $carrierCode) {
                return $quote;
            }
        }

        return null;
    }

    /**
     * @param callable(RateQuote): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(...array_filter($this->quotes, $predicate));
    }

    /**
     * @param callable(RateQuote): RateQuote $fn
     */
    public function map(callable $fn): self
    {
        return new self(...array_map($fn, $this->quotes));
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->quotes);
    }
}
