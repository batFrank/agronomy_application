<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Transformer\Shared\QueryParamTransformer;

use App\Domain\Shared\Criteria\InvalidSortException;
use App\Domain\Shared\Criteria\SortDirection;
use App\Infrastructure\Transformer\Shared\QueryParamTransformer\LifeCycleSortableTransformer;
use PHPUnit\Framework\TestCase;

class LifeCycleSortableTransformerTest extends TestCase
{
    public function testGetSortableFieldsReturnsLifeCycleSupportedFields(): void
    {
        $this->assertSame(
            [
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            LifeCycleSortableTransformer::getSortableFields(),
        );
    }

    /**
     * @throws InvalidSortException
     */
    public function testToSortableFieldTransformsCreatedAtField(): void
    {
        $sortableField = LifeCycleSortableTransformer::toSortableField('created_at');

        $this->assertSame('createdAt', $sortableField->value());
    }

    /**
     * @throws InvalidSortException
     */
    public function testToSortableFieldTransformsUpdatedAtField(): void
    {
        $sortableField = LifeCycleSortableTransformer::toSortableField('updated_at');

        $this->assertSame('updatedAt', $sortableField->value());
    }

    public function testToSortableFieldThrowsOnInvalidField(): void
    {
        $this->expectException(InvalidSortException::class);
        $this->expectExceptionMessage('Field invalid is not sortable.');

        LifeCycleSortableTransformer::toSortableField('invalid');
    }

    public function testToSortDirectionMapsDescAndDefaultsAsc(): void
    {
        $this->assertSame(SortDirection::DESC, LifeCycleSortableTransformer::toSortDirection('desc'));
        $this->assertSame(SortDirection::ASC, LifeCycleSortableTransformer::toSortDirection('whatever'));
    }
}
