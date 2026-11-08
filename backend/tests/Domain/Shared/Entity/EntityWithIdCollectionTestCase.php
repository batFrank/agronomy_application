<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Entity;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Entity\EntityWithId;
use App\Domain\Shared\Entity\EntityWithIdCollection;
use App\Tests\Domain\Shared\Collection\CollectionTestCase;

/**
 * @template T of EntityWithId
 *
 * @extends CollectionTestCase<T>
 */
abstract class EntityWithIdCollectionTestCase extends CollectionTestCase
{
    /**
     * @throws CollectionException
     */
    public function testAllIndexedById(): void
    {
        /** @var EntityWithId $item1 */
        $item1 = $this->getItem();
        /** @var EntityWithId $item2 */
        $item2 = $this->getItem();
        /** @var EntityWithId $item3 */
        $item3 = $this->getItem();

        /** @var EntityWithIdCollection<T> $collection */
        $collection = $this->createCollection([
            $item1,
            $item2,
            $item3,
        ]);

        $collectionIndexedById = $collection->allIndexedById();

        $this->assertCount(3, $collectionIndexedById);
        $this->assertSame($item1, $collectionIndexedById[$item1->getId()->value()]);
        $this->assertSame($item2, $collectionIndexedById[$item2->getId()->value()]);
        $this->assertSame($item3, $collectionIndexedById[$item3->getId()->value()]);
    }
}
