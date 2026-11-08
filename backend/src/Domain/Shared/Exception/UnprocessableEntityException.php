<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

/**
 * Parent class for exceptions when an entity cannot be processed because of some criteria. Useful for handlers.
 */
class UnprocessableEntityException extends \RuntimeException
{
    protected $message = 'Unprocessable entity.';
}
