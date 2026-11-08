<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

/**
 * Parent class of the exceptions for when a dependency outside the application fails to answer (an
 * external provider, for instance). It answers 502, not 503: the problem is that the other end
 * failed, not that this service is down or overloaded.
 *
 * It is loggable, so the full detail (the chain of `previous` included) reaches the log, while the
 * client only sees the message of the exception: give it a generic one, never the text of the failure
 * it wraps, which can carry hosts, headers or anything the provider said.
 */
class BadGatewayException extends \RuntimeException implements LoggableException
{
    protected $message = 'Bad gateway.';
}
