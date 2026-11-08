<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Transformer\User;

use App\Domain\Shared\Criteria\InvalidSortException;
use App\Domain\Shared\Criteria\SortDirection;
use App\Infrastructure\Transformer\User\UserSortableTransformer;
use PHPUnit\Framework\TestCase;

class UserSortableTransformerTest extends TestCase
{
    public function testGetSortableFieldsReturnsUserSupportedFields(): void
    {
        $this->assertSame(
            [
                'email' => 'email',
            ],
            UserSortableTransformer::getSortableFields(),
        );
    }

    /**
     * @throws InvalidSortException
     */
    public function testToSortableFieldTransformsUserField(): void
    {
        $sortableField = UserSortableTransformer::toSortableField('email');

        $this->assertSame('email', $sortableField->value());
    }

    /**
     * @throws InvalidSortException
     */
    public function testToSortableFieldTransformsLifeCycleField(): void
    {
        $sortableField = UserSortableTransformer::toSortableField('created_at');

        $this->assertSame('createdAt', $sortableField->value());
    }

    public function testToSortableFieldThrowsOnInvalidField(): void
    {
        $this->expectException(InvalidSortException::class);
        $this->expectExceptionMessage('Field invalid is not sortable.');

        UserSortableTransformer::toSortableField('invalid');
    }

    public function testToSortDirectionMapsDescAndDefaultsAsc(): void
    {
        $this->assertSame(SortDirection::DESC, UserSortableTransformer::toSortDirection('desc'));
        $this->assertSame(SortDirection::ASC, UserSortableTransformer::toSortDirection('whatever'));
    }
}
