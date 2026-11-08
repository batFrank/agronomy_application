<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Transformer\User;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\Sort;
use App\Domain\Shared\Criteria\SortDirection;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\UserRole;
use App\Infrastructure\Transformer\User\UserCriteriaTransformer;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\TestCase;

class UserCriteriaTransformerTest extends TestCase
{
    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testFromArrayTransformsAllSupportedFilters(): void
    {
        $createdBy = UuidMother::create();

        $criteria = UserCriteriaTransformer::fromArray([
            'search' => 'john',
            'role' => 'ROLE_ADMIN',
            'created_by' => $createdBy->value(),
            'sort_by' => 'email',
            'sort_dir' => 'desc',
            'page' => 2,
            'limit' => 25,
        ]);

        $this->assertSame('john', $criteria->getSearchString());
        $this->assertSame(UserRole::Admin, $criteria->getRole());
        $this->assertSame($createdBy->value(), $criteria->getCreatedBy()?->value());

        $pagination = $criteria->getPagination();
        $this->assertNotNull($pagination);
        $this->assertSame(2, $pagination->page());
        $this->assertSame(25, $pagination->limit());

        $sortBy = $criteria->getSortBy();
        $this->assertNotNull($sortBy);
        $this->assertCount(1, $sortBy);

        $sort = $sortBy->first();
        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('email', $sort->getField()->value());
        $this->assertSame(SortDirection::DESC, $sort->getDirection());
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testFromArrayAppliesFallbacksAndDefaultPagination(): void
    {
        $criteria = UserCriteriaTransformer::fromArray([
            'role' => 'ROLE_UNKNOWN',
        ]);

        $this->assertSame(UserRole::User, $criteria->getRole());

        $pagination = $criteria->getPagination();
        $this->assertNotNull($pagination);
        $this->assertSame(1, $pagination->page());
        $this->assertSame(10, $pagination->limit());
    }

    /**
     * @throws CollectionException
     */
    public function testFromArrayThrowsOnInvalidCreatedByUuid(): void
    {
        $this->expectException(InvalidUuidException::class);

        UserCriteriaTransformer::fromArray([
            'created_by' => 'invalid-uuid',
        ]);
    }
}
