<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence;

use App\Domain\Shared\Criteria\Sort;
use App\Domain\Shared\Criteria\SortableField;
use App\Domain\Shared\Criteria\SortDirection;
use App\Infrastructure\Persistence\DBALSortTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DBALSortTransformerTest extends TestCase
{
    #[DataProvider('sortDirectionProvider')]
    public function testFromSortDirection(SortDirection $direction, string $expected): void
    {
        $result = DBALSortTransformer::fromSortDirection($direction);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{direction: SortDirection, expected: string}>
     */
    public static function sortDirectionProvider(): array
    {
        return [
            'ascending' => [
                'direction' => SortDirection::ASC,
                'expected' => 'ASC',
            ],
            'descending' => [
                'direction' => SortDirection::DESC,
                'expected' => 'DESC',
            ],
        ];
    }

    public function testFromSortByToDbalSortBy(): void
    {
        $sort = $this->createMock(Sort::class);
        $sort->expects(self::once())
            ->method('getField')
            ->willReturn(new SortableField('createdAt'));

        $result = DBALSortTransformer::fromSortByToDbalSortBy($sort);

        $this->assertSame('created_at', $result);
    }

    public function testFromSortByToDbalSortByWithMap(): void
    {
        $sort = $this->createMock(Sort::class);
        $sort->expects(self::once())
            ->method('getField')
            ->willReturn(new SortableField('createdAt'));

        // The map overrides the default snake_case conversion with a custom column name
        $result = DBALSortTransformer::fromSortByToDbalSortBy($sort, ['createdAt' => 'custom_column_name']);

        $this->assertSame('custom_column_name', $result);
    }
}
