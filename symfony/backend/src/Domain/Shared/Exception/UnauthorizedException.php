<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

/**
 * Parent exception, when an action requires the user to be authenticated.
 */
class UnauthorizedException extends \RuntimeException
{
    public function __construct(
        string $message = 'Unauthorized.',
    ) {
        parent::__construct($message);
    }
}
