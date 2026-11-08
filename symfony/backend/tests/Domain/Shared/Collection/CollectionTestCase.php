<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Collection;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use PHPUnit\Framework\TestCase;

/**
 * @template T
 */
abstract class CollectionTestCase extends TestCase
{
    /**
     * @param array<int, object> $items
     *
     * @return Collection<object>
     *
     * @throws CollectionException
     */
    abstract protected function createCollection(array $items): Collection;

    abstract protected function getExpectedType(): string;

    abstract protected function getItem(): object;

    public function testOnlyItemsOfExpectedTypeCanBeAdded(): void
    {
        $newItem = new \stdClass();
        $className = \get_class($newItem);

        $this->expectExceptionObject(
            CollectionException::invalidItemType($this->getExpectedType(), $className)
        );

        $this->createCollection([$newItem]);
    }

    /**
     * @throws CollectionException
     */
    public function testOnlyItemsOfExpectedTypeCanBeAppend(): void
    {
        $newItem = new \stdClass();
        $className = \get_class($newItem);

        $this->expectExceptionObject(
            CollectionException::invalidItemType($this->getExpectedType(), $className)
        );

        $collection = $this->createCollection([]);
        $collection->append($newItem);
    }

    /**
     * @throws CollectionException
     */
    public function testOnlyReplaceItemsOfExpectedType(): void
    {
        $newItem = new \stdClass();
        $className = \get_class($newItem);

        $this->expectExceptionObject(
            CollectionException::invalidItemType($this->getExpectedType(), $className)
        );

        $collection = $this->createCollection([$this->getItem()]);
        $collection->replace([$newItem]);
    }
}
