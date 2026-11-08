<?php

declare(strict_types=1);

namespace App\Tests\Domain\User;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\User\PaginatedUserCollection;
use App\Domain\User\UserCollection;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaginatedUserCollectionTest extends TestCase
{
    /**
     * Test that the constructor sets the collection and total items correctly.
     *
     * @throws CollectionException
     */
    public function testConstructor(): void
    {
        // Arrange
        $collection = new UserCollection([]);
        $totalItems = 100;

        // Act
        $paginatedCollection = new PaginatedUserCollection($collection, $totalItems);

        // Assert
        $this->assertSame($collection, $paginatedCollection->getCollection());
        $this->assertSame($totalItems, $paginatedCollection->getTotalItems());
    }

    /**
     * Test that getCollection returns the correct collection.
     *
     * @throws CollectionException
     */
    public function testGetCollection(): void
    {
        // Arrange
        $collection = new UserCollection([]);
        $paginatedCollection = new PaginatedUserCollection($collection, 100);

        // Act
        $result = $paginatedCollection->getCollection();

        // Assert
        $this->assertSame($collection, $result);
    }

    /**
     * Test that getTotalItems returns the correct total items.
     *
     * @throws CollectionException
     */
    public function testGetTotalItems(): void
    {
        // Arrange
        $totalItems = 100;
        $paginatedCollection = new PaginatedUserCollection(new UserCollection([]), $totalItems);

        // Act
        $result = $paginatedCollection->getTotalItems();

        // Assert
        $this->assertSame($totalItems, $result);
    }

    /**
     * Test that the constructor throws an exception if the collection is not a UserCollection.
     *
     * @throws Exception
     */
    public function testConstructorThrowsExceptionForInvalidCollection(): void
    {
        // Arrange
        /** @var UserCollection&MockObject $invalidCollection */
        $invalidCollection = $this->createStub(Collection::class);

        // Assert
        $this->expectException(\TypeError::class);

        // Act
        $result = new PaginatedUserCollection($invalidCollection, 100);
    }
}
