<?php

declare(strict_types=1);

namespace App\Infrastructure\Request\Exception;

class RequestAttributeExtractorException extends \Exception
{
    public static function userNotFound(): self
    {
        return new self('User not found in request attributes');
    }
}
