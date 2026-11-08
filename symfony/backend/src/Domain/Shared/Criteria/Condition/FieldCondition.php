<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria\Condition;

/**
 * By default, all repositories try to do an equality check.
 * If a different condition is needed, this class can be used.
 *
 * For a DBAL repository, it is not required to try to check all defined conditions.
 * Repository criteria and task conditions define what conditions are allowed.
 *
 * @example function (string $value, Condition $condition = Condition::Equal): self
 * {
 *     $this->someCondition = new FieldCondition($value, $condition);
 *     return $this;
 * }
 */
final readonly class FieldCondition
{
    public function __construct(
        private mixed $value,
        private Condition $condition = Condition::Equal,
    ) {
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function getCondition(): Condition
    {
        return $this->condition;
    }
}
