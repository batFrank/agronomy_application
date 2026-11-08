<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Criteria;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\CriteriaException;
use App\Domain\Shared\Criteria\CriteriaWithId;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\UuidCollection;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\TestCase;

class CriteriaWithIdTest extends TestCase
{
    /** @phpstan-ignore missingType.generics */
    private CriteriaWithId $testCriteriaClass;

    protected function setUp(): void
    {
        $this->testCriteriaClass = new class extends CriteriaWithId {};
    }

    /**
     * @throws InvalidUuidException
     */
    public function testCreateById(): void
    {
        $id = UuidMother::create();

        $criteria1 = $this->testCriteriaClass::createById($id);
        $criteria2 = $this->testCriteriaClass::createEmpty()->filterById($id);

        $this->assertEquals($criteria2->getId(), $criteria1->getId());
        $this->assertSame($id, $criteria1->getId());
    }

    /**
     * @throws CollectionException
     * @throws CriteriaException
     * @throws InvalidUuidException
     */
    public function testCreateByIds(): void
    {
        $ids = new UuidCollection([
            UuidMother::create(),
            UuidMother::create(),
        ]);

        $criteria1 = $this->testCriteriaClass::createByIds($ids);
        $criteria2 = $this->testCriteriaClass::createEmpty()->filterByIds($ids);

        $this->assertEquals($criteria2->getIds(), $criteria1->getIds());
        $this->assertSame($ids, $criteria1->getIds());
    }

    /**
     * @throws CriteriaException
     */
    public function testCreateByIdsThrowsExceptionWithEmptyCollection(): void
    {
        $this->expectException(CriteriaException::class);

        $emptyIds = new UuidCollection();
        $this->testCriteriaClass::createByIds($emptyIds);
    }

    /**
     * @throws InvalidUuidException
     */
    public function testFilterByIdOverwritesPreviousId(): void
    {
        $firstId = UuidMother::create();
        $secondId = UuidMother::create();

        $criteria = $this->testCriteriaClass::createEmpty()
            ->filterById($firstId)
            ->filterById($secondId);

        $this->assertSame($secondId, $criteria->getId());
    }

    /**
     * @throws CriteriaException
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testFilterByIdsOverwritesPreviousIds(): void
    {
        $firstIds = new UuidCollection([
            UuidMother::create(),
            UuidMother::create(),
        ]);
        $secondIds = new UuidCollection([
            UuidMother::create(),
            UuidMother::create(),
        ]);

        $criteria = $this->testCriteriaClass::createEmpty()
            ->filterByIds($firstIds)
            ->filterByIds($secondIds);

        $this->assertSame($secondIds, $criteria->getIds());
    }

    /**
     * @throws CriteriaException
     */
    public function testFilterByEmptyIdsThrowsException(): void
    {
        $this->expectException(CriteriaException::class);

        $emptyIds = new UuidCollection();
        $this->testCriteriaClass::createEmpty()->filterByIds($emptyIds);
    }

    public function testEmptyCriteriaHasNoIdOrIds(): void
    {
        $criteria = $this->testCriteriaClass::createEmpty();

        $this->assertNull($criteria->getId());
        $this->assertNull($criteria->getIds());
    }
}
