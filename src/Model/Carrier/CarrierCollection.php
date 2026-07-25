<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Carrier;

/**
 * Immutable, iterable set of Carrier.
 *
 * @implements \IteratorAggregate<int, Carrier>
 */
final readonly class CarrierCollection implements \IteratorAggregate, \Countable
{
    /** @var list<Carrier> */
    private array $carriers;

    public function __construct(Carrier ...$carriers)
    {
        $this->carriers = array_values($carriers);
    }

    /** @return list<Carrier> */
    public function all(): array
    {
        return $this->carriers;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_map(static fn (Carrier $c): string => $c->code, $this->carriers);
    }

    public function has(string $code): bool
    {
        return \in_array($code, $this->codes(), true);
    }

    public function isEmpty(): bool
    {
        return [] === $this->carriers;
    }

    public function count(): int
    {
        return \count($this->carriers);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->carriers);
    }
}
