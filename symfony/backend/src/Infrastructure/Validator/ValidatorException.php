<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidatorException extends \Exception
{
    protected $message = 'Validation failed';
    private ?ConstraintViolationListInterface $violations = null;

    public static function createByViolations(ConstraintViolationListInterface $violations): self
    {
        $exception = new self();
        $exception->violations = $violations;

        return $exception;
    }

    public static function createByMessage(string $message): self
    {
        return new self($message);
    }

    public function getViolations(): ?ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
