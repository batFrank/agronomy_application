<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Transformer\Shared\QueryParamTransformer;

use App\Domain\Shared\Criteria\InvalidSortException;
use App\Domain\Shared\Criteria\SortableField;
use App\Domain\Shared\Criteria\SortDirection;
use App\Infrastructure\Transformer\Shared\QueryParamTransformer\SortableTransformer;
use PHPUnit\Framework\TestCase;

class SortableTransformerTest extends TestCase
{
    private SortableTransformer $testSortableTransformer;

    protected function setUp(): void
    {
        $this->testSortableTransformer = new class extends SortableTransformer {
            public static function getSortableFields(): array
            {
                return [];
            }

            /**
             * @throws InvalidSortException
             */
            public static function toSortableField(string $sortBy): SortableField
            {
                throw InvalidSortException::invalidField($sortBy);
            }
        };
    }

    public function testToSortDirectionMapsDescCaseInsensitive(): void
    {
        $this->assertSame(SortDirection::DESC, $this->testSortableTransformer::toSortDirection('DESC'));
        $this->assertSame(SortDirection::DESC, $this->testSortableTransformer::toSortDirection('desc'));
    }

    public function testToSortDirectionDefaultsToAsc(): void
    {
        $this->assertSame(SortDirection::ASC, $this->testSortableTransformer::toSortDirection('asc'));
        $this->assertSame(SortDirection::ASC, $this->testSortableTransformer::toSortDirection('anything'));
    }
}
