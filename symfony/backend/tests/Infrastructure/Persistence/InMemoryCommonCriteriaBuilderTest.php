<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence;

use App\Domain\Shared\Criteria\Condition\Condition;
use App\Domain\Shared\Criteria\Condition\FieldCondition;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\Persistence\InMemoryCommonCriteriaBuilder;
use App\Infrastructure\Transformer\DateTimeTransformer;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryCommonCriteriaBuilder::class)]
class InMemoryCommonCriteriaBuilderTest extends TestCase
{
    #[DataProvider('provideValidateConditionCases')]
    public function testValidateCondition(mixed $value, FieldCondition $condition, bool $expected): void
    {
        $this->assertEquals(
            $expected,
            InMemoryCommonCriteriaBuilder::validateCondition(value: $value, condition: $condition)
        );
    }

    /**
     * @throws \DateMalformedStringException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public static function provideValidateConditionCases(): \Generator
    {
        yield 'is null on null value' => [
            'value' => null,
            'condition' => new FieldCondition(
                value: null,
                condition: Condition::IsNull
            ),
            'expected' => true,
        ];

        yield 'is null on non null value' => [
            'value' => 'some value',
            'condition' => new FieldCondition(
                value: null,
                condition: Condition::IsNull
            ),
            'expected' => false,
        ];

        yield 'is not null on null value' => [
            'value' => null,
            'condition' => new FieldCondition(
                value: null,
                condition: Condition::IsNotNull
            ),
            'expected' => false,
        ];

        yield 'is not null on non null value' => [
            'value' => 'some value',
            'condition' => new FieldCondition(
                value: null,
                condition: Condition::IsNotNull
            ),
            'expected' => true,
        ];

        yield 'not equals on scalar when not equals' => [
            'value' => 'some value',
            'condition' => new FieldCondition(
                value: 'different value',
                condition: Condition::NotEqual
            ),
            'expected' => true,
        ];

        yield 'not equals on scalar when equals' => [
            'value' => 'same value',
            'condition' => new FieldCondition(
                value: 'same value',
                condition: Condition::NotEqual
            ),
            'expected' => false,
        ];

        yield 'not equals on object when not equals and method equals method' => [
            'value' => UuidMother::create(),
            'condition' => new FieldCondition(
                value: UuidMother::create(),
                condition: Condition::NotEqual
            ),
            'expected' => true,
        ];

        $id = UuidMother::create();
        yield 'not equals on object when equals and method equals method' => [
            'value' => $id,
            'condition' => new FieldCondition(
                value: $id,
                condition: Condition::NotEqual
            ),
            'expected' => false,
        ];

        yield 'not equals on object when not equals (excepcional case)' => [
            'value' => UserMother::create(),
            'condition' => new FieldCondition(
                value: UserMother::create(),
                condition: Condition::NotEqual
            ),
            'expected' => true,
        ];

        $object = UserMother::create();
        yield 'not equals on object when equals (excepcional case)' => [
            'value' => $object,
            'condition' => new FieldCondition(
                value: $object,
                condition: Condition::NotEqual
            ),
            'expected' => false,
        ];

        yield 'equals on scalar when not equals' => [
            'value' => 'some value',
            'condition' => new FieldCondition(
                value: 'different value',
                condition: Condition::Equal
            ),
            'expected' => false,
        ];

        yield 'equals on scalar when equals' => [
            'value' => 'same value',
            'condition' => new FieldCondition(
                value: 'same value',
                condition: Condition::Equal
            ),
            'expected' => true,
        ];

        yield 'equals on object when not equals and method equals method' => [
            'value' => UuidMother::create(),
            'condition' => new FieldCondition(
                value: UuidMother::create(),
                condition: Condition::Equal
            ),
            'expected' => false,
        ];

        yield 'equals on object when equals and method equals method' => [
            'value' => $id,
            'condition' => new FieldCondition(
                value: $id,
                condition: Condition::Equal
            ),
            'expected' => true,
        ];

        yield 'equals on object when not equals (excepcional case)' => [
            'value' => UserMother::create(),
            'condition' => new FieldCondition(
                value: UserMother::create(),
                condition: Condition::Equal
            ),
            'expected' => false,
        ];

        $object = UserMother::create();
        yield 'equals on object when equals (excepcional case)' => [
            'value' => $object,
            'condition' => new FieldCondition(
                value: $object,
                condition: Condition::Equal
            ),
            'expected' => true,
        ];

        // Date time test cases
        yield 'equals on datetime when equals (excepcional case)' => [
            'value' => DateTimeTransformer::fromInput('2025-01-01 12:00:00'),
            'condition' => new FieldCondition(
                value: DateTimeTransformer::fromInput('2025-01-01 12:00:00'),
                condition: Condition::Equal
            ),
            'expected' => true,
        ];

        yield 'equals on datetime when not equals (excepcional case)' => [
            'value' => DateTimeTransformer::fromInput('2025-01-01 12:00:00'),
            'condition' => new FieldCondition(
                value: DateTimeTransformer::fromInput('2025-01-01 13:00:00'),
                condition: Condition::Equal
            ),
            'expected' => false,
        ];

        yield 'not equals on datetime when equals (excepcional case)' => [
            'value' => DateTimeTransformer::fromInput('2025-01-01 12:00:00'),
            'condition' => new FieldCondition(
                value: DateTimeTransformer::fromInput('2025-01-01 12:00:00'),
                condition: Condition::NotEqual
            ),
            'expected' => false,
        ];

        yield 'not equals on datetime when not equals (excepcional case)' => [
            'value' => DateTimeTransformer::fromInput('2025-01-01 12:00:00'),
            'condition' => new FieldCondition(
                value: DateTimeTransformer::fromInput('2025-01-01 12:00:01'),
                condition: Condition::NotEqual
            ),
            'expected' => true,
        ];
    }
}
