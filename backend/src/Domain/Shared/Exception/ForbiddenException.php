<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

/**
 * Parent class of user defined exceptions. When an action is not allowed to be executed by the user.
 */
class ForbiddenException extends \RuntimeException
{
    protected $message = 'Forbidden.';
}
