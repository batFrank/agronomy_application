<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\CriteriaException;
use App\Domain\Shared\Criteria\Pagination;
use App\Domain\Shared\Criteria\Sort;
use App\Domain\Shared\Criteria\SortableField;
use App\Domain\Shared\Criteria\SortCollection;
use App\Domain\Shared\Criteria\SortDirection;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\UuidCollection;
use App\Domain\User\UserCriteria;
use App\Infrastructure\Persistence\DBALCommonCriteriaBuilder;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class DBALCommonCriteriaBuilderTest extends TestCase
{
    /**
     * @throws DBALException
     */
    public function testItAppliesPaginationAndDefaultSortingWhenNoSortIsProvided(): void
    {
        $criteria = UserCriteria::createEmpty()
            ->withPagination(new Pagination(3, 5));

        $queryBuilder = $this->createQueryBuilder();

        DBALCommonCriteriaBuilder::filterByCommonCriteria($criteria, $queryBuilder);

        self::assertSame(10, $queryBuilder->getFirstResult());
        self::assertSame(5, $queryBuilder->getMaxResults());
        self::assertStringContainsString('ORDER BY t.id ASC', $queryBuilder->getSQL());
    }

    /**
     * @throws DBALException
     * @throws CollectionException
     */
    public function testItConvertsSortFieldsToSnakeCase(): void
    {
        $sorts = new SortCollection([
            new Sort(new SortableField('createdAt'), SortDirection::ASC),
            new Sort(new SortableField('lastLoginAt'), SortDirection::DESC),
        ]);

        $criteria = UserCriteria::createEmpty()->sortBy($sorts);
        $queryBuilder = $this->createQueryBuilder();

        DBALCommonCriteriaBuilder::filterByCommonCriteria($criteria, $queryBuilder);

        $sql = $queryBuilder->getSQL();

        self::assertStringContainsString(
            'ORDER BY t.created_at ASC, t.last_login_at DESC',
            $sql,
        );
    }

    /**
     * @throws DBALException
     * @throws CollectionException
     */
    public function testItUsesCustomSortMapColumnNameOverSnakeCase(): void
    {
        $sorts = new SortCollection([
            new Sort(new SortableField('createdAt'), SortDirection::ASC),
        ]);

        $criteria = UserCriteria::createEmpty()->sortBy($sorts);
        $queryBuilder = $this->createQueryBuilder();

        DBALCommonCriteriaBuilder::filterByCommonCriteria($criteria, $queryBuilder, false, ['createdAt' => 'custom_column_name']);

        self::assertStringContainsString('ORDER BY t.custom_column_name ASC', $queryBuilder->getSQL());
    }

    /**
     * @throws DBALException
     * @throws InvalidUuidException
     */
    public function testItFiltersBySingleId(): void
    {
        $uuid = UuidMother::create();
        $criteria = UserCriteria::createById($uuid);

        $queryBuilder = $this->createQueryBuilder();

        DBALCommonCriteriaBuilder::filterByCommonCriteria($criteria, $queryBuilder, true);

        self::assertStringContainsString('WHERE t.id = :id', $queryBuilder->getSQL());
        self::assertSame($uuid->value(), $queryBuilder->getParameter('id'));
    }

    /**
     * @throws CollectionException
     * @throws DBALException
     * @throws InvalidUuidException
     * @throws CriteriaException
     */
    public function testItFiltersByIdsAndKeepsArrayParameterType(): void
    {
        $ids = new UuidCollection([
            UuidMother::create(),
            UuidMother::create(),
        ]);

        $criteria = UserCriteria::createEmpty()->filterByIds($ids);
        $queryBuilder = $this->createQueryBuilder();

        DBALCommonCriteriaBuilder::filterByCommonCriteria($criteria, $queryBuilder, true);

        self::assertStringContainsString('t.id in (:ids)', $queryBuilder->getSQL());
        self::assertSame($ids->all(), $queryBuilder->getParameter('ids'));
        self::assertSame(
            ArrayParameterType::STRING,
            $queryBuilder->getParameterTypes()['ids'] ?? null,
        );
    }

    /**
     * @throws DBALException
     */
    private function createQueryBuilder(): QueryBuilder
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
        ]);

        return $connection
            ->createQueryBuilder()
            ->select('t.id')
            ->from('dummy_table', 't');
    }
}
