<?php

declare(strict_types=1);

namespace App\Domain\Shared\Collection;

/**
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
abstract class Collection implements \IteratorAggregate, \Countable
{
    /**
     * @var T[]
     */
    protected array $items = [];

    /**
     * @param T[] $items
     *
     * @throws CollectionException
     */
    final public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->append($item);
        }
    }

    abstract public function getTypeName(): string;

    /**
     * @param T $item
     *
     * @throws CollectionException
     */
    protected function checkType($item): void
    {
        $typeName = $this->getTypeName();

        if (enum_exists($typeName) && $item instanceof $typeName) {
            return;
        }

        if ('object' === \gettype($item) && $item instanceof $typeName) {
            return;
        }

        if ($typeName === \gettype($item)) {
            return;
        }

        throw CollectionException::invalidItemType(
            expectedType: $typeName,
            actualType: 'object' === \gettype($item) ? \get_class($item) : \gettype($item)
        );
    }

    /**
     * @return T[]
     */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * @param T $item
     *
     * @throws CollectionException
     */
    public function append($item): void
    {
        $this->checkType($item);

        $this->items[] = $item;
    }

    /**
     * @param T[] $items
     *
     * @throws CollectionException
     */
    public function replace(array $items): static
    {
        foreach ($items as $item) {
            $this->checkType($item);
        }

        $this->items = $items;

        return $this;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @throws CollectionException
     */
    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * @throws CollectionException
     */
    public function filter(callable $callback): static
    {
        return new static(array_values(array_filter($this->items, $callback)));
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * @param T $item
     */
    public function contains($item): bool
    {
        return \in_array($item, $this->items, true);
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        $key = array_key_first($this->items);

        return null !== $key ? $this->items[$key] : null;
    }

    /**
     * @return T|null
     */
    public function last(): mixed
    {
        $key = array_key_last($this->items);

        return null !== $key ? $this->items[$key] : null;
    }

    /**
     * @return T
     *
     * @throws CollectionException
     */
    public function random(): mixed
    {
        if ($this->isEmpty()) {
            throw CollectionException::emptyCollection();
        }

        $key = array_rand($this->items);

        return $this->items[$key];
    }
}
