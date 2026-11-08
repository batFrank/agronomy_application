<?php

declare(strict_types=1);

namespace App\Tests\Domain\User;

use App\Domain\User\UserCriteria;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\TestCase;

class UserCriteriaTest extends TestCase
{
    public function testFilterByCreatedBy(): void
    {
        $uuid = UuidMother::create();
        $criteria = UserCriteria::createEmpty()->filterByCreatedBy($uuid);

        $this->assertSame($uuid, $criteria->getCreatedBy());
    }

    public function testGetCreatedByReturnsNullByDefault(): void
    {
        $criteria = UserCriteria::createEmpty();

        $this->assertNull($criteria->getCreatedBy());
    }

    public function testIsEmptyReturnsFalseWhenCreatedByIsSet(): void
    {
        $uuid = UuidMother::create();
        $criteria = UserCriteria::createEmpty()->filterByCreatedBy($uuid);

        $this->assertFalse($criteria->isEmpty());
    }

    public function testIsEmptyReturnsTrueWhenNoFiltersAreSet(): void
    {
        $criteria = UserCriteria::createEmpty();

        $this->assertTrue($criteria->isEmpty());
    }
}
