<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Collection;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    /** @var Collection<int> */
    private Collection $collection;

    protected function setUp(): void
    {
        $this->collection = new class([1, 2, 3]) extends Collection {
            public function getTypeName(): string
            {
                return \gettype(1);
            }
        };
    }

    public function testConstructorSetsItems(): void
    {
        $this->assertSame([1, 2, 3], $this->collection->all());
    }

    public function testCount(): void
    {
        $this->assertCount(3, $this->collection);
    }

    public function testIsEmpty(): void
    {
        $this->assertFalse($this->collection->isEmpty());

        $emptyCollection = new class([]) extends Collection {
            public function getTypeName(): string
            {
                return 'string';
            }
        };

        $this->assertTrue($emptyCollection->isEmpty());
    }

    /**
     * @throws CollectionException
     */
    public function testAppend(): void
    {
        $this->collection->append(4);
        $this->assertSame([1, 2, 3, 4], $this->collection->all());
    }

    /**
     * @throws \Exception
     */
    public function testGetIterator(): void
    {
        $this->assertInstanceOf(\ArrayIterator::class, $this->collection->getIterator());
    }

    public function testMap(): void
    {
        $mappedCollection = $this->collection->map(fn ($item) => $item * 2);
        $this->assertSame([2, 4, 6], $mappedCollection->all());
    }

    public function testFilter(): void
    {
        $filteredCollection = $this->collection->filter(fn ($item) => $item > 1);
        $this->assertSame([2, 3], $filteredCollection->all());
    }

    /**
     * @throws CollectionException
     */
    public function testReduce(): void
    {
        $sum = $this->collection->reduce(fn ($carry, $item) => $carry + $item, 0);
        $this->assertSame(6, $sum);
    }

    public function testContains(): void
    {
        $this->assertTrue($this->collection->contains(2));
        $this->assertFalse($this->collection->contains(4));
    }

    public function testCreateEmptyCollection(): void
    {
        $emptyCollection = new class extends Collection {
            public function getTypeName(): string
            {
                return 'string';
            }
        };

        $this->assertTrue($emptyCollection->isEmpty());
    }

    /**
     * @throws CollectionException
     */
    public function testRandomThrowsExceptionOnEmptyCollection(): void
    {
        $emptyCollection = new class([]) extends Collection {
            public function getTypeName(): string
            {
                return 'integer';
            }
        };

        $this->expectException(CollectionException::class);
        $this->expectExceptionMessage('Collection is empty');

        $emptyCollection->random();
    }

    /**
     * @throws CollectionException
     */
    public function testRandomReturnsElementFromCollection(): void
    {
        $result = $this->collection->random();

        $this->assertContains($result, [1, 2, 3]);
    }

    /**
     * @throws CollectionException
     */
    public function testRandomReturnsSingleElementFromSingleItemCollection(): void
    {
        $singleCollection = new class([42]) extends Collection {
            public function getTypeName(): string
            {
                return 'integer';
            }
        };

        $result = $singleCollection->random();

        $this->assertSame(42, $result);
    }
}
