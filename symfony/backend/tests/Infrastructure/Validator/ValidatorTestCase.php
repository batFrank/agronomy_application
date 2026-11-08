<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidatorTestCase extends TestCase
{
    /**
     * Asserts that the given violation list matches the expected violations.
     *
     * The expected violations array should have the property path as key.
     * The value can be a string or an array of strings.
     * If it is an array, the message must be one of the strings in the array.
     * If the value is a string, the message must match exactly.
     * The order of the violations does not matter.
     * The property path must be the full path, e.g. "assignment_id".
     * The message must be the exact message of the violation.
     *
     * Example:
     * [
     *   '[assignment_id]' => ['This value should not be null.'],
     * ]
     * or
     * [
     *   '[assignment_id]' => [
     *     'This value should be of type string.',
     *     'This is not a valid UUID.',
     *   ],
     * ]
     *
     * The $violationList is the result of a validation call
     * using $e->getViolations() from the ViolatedConstraintsException.
     *
     * @param array<string, mixed> $expectedViolations
     */
    protected function assertViolations(
        array $expectedViolations,
        ConstraintViolationListInterface $violationList,
    ): void {
        foreach ($violationList as $violation) {
            $this->assertArrayHasKey($violation->getPropertyPath(), $expectedViolations);
            if (\is_array($expectedViolations[$violation->getPropertyPath()])) {
                $this->assertContains($violation->getMessage(), $expectedViolations[$violation->getPropertyPath()]);
            } else {
                $this->assertEquals($expectedViolations[$violation->getPropertyPath()], $violation->getMessage());
            }
        }

        foreach ($expectedViolations as $propertyPath => $expectedViolation) {
            $found = false;
            foreach ($violationList as $violation) {
                if ($violation->getPropertyPath() === $propertyPath) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $this->fail("Expected violation for property path $propertyPath with message '$expectedViolation' not found.");
            }
        }
    }
}
