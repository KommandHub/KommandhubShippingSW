<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Tracking;

/**
 * An immutable, iterable set of TrackingEvent. latest() is the event the
 * delivery state machine acts on — the most recent by occurrence time, not by
 * arrival order, since webhooks and polls can deliver out of sequence.
 *
 * @implements \IteratorAggregate<int, TrackingEvent>
 */
final readonly class TrackingEventCollection implements \IteratorAggregate, \Countable
{
    /** @var list<TrackingEvent> */
    private array $events;

    public function __construct(TrackingEvent ...$events)
    {
        $this->events = array_values($events);
    }

    /** @return list<TrackingEvent> */
    public function all(): array
    {
        return $this->events;
    }

    public function isEmpty(): bool
    {
        return [] === $this->events;
    }

    public function count(): int
    {
        return \count($this->events);
    }

    /** New collection ordered oldest first. */
    public function sortedByTime(): self
    {
        $sorted = $this->events;
        usort($sorted, static fn (TrackingEvent $a, TrackingEvent $b): int => $a->occurredAt <=> $b->occurredAt);

        return new self(...$sorted);
    }

    public function latest(): ?TrackingEvent
    {
        if ($this->isEmpty()) {
            return null;
        }

        $sorted = $this->sortedByTime()->all();

        return $sorted[\count($sorted) - 1];
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->events);
    }
}
