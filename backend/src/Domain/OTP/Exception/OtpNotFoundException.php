<?php

declare(strict_types=1);

namespace App\Domain\OTP\Exception;

use App\Domain\Shared\Exception\NotFoundException;

final class OtpNotFoundException extends NotFoundException
{
    public function __construct(string $message = 'OTP not found')
    {
        parent::__construct($message);
    }
}
