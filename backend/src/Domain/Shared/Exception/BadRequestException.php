<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

/**
 * Parent class of the exceptions for when a request is malformed: its shape is wrong, whatever the
 * state of the application. It answers 400.
 *
 * It is not loggable, on purpose: a malformed request is the caller's mistake and a 400 is not logged
 * (the same as the other 4xx of the listener, except the 422).
 */
class BadRequestException extends \RuntimeException
{
    protected $message = 'Bad request.';
}
