<?php

declare(strict_types=1);

namespace App\Domain\Security\Exceptions;

use App\Domain\Shared\Exception\NotFoundException;

class RefreshTokenNotFoundException extends NotFoundException
{
    protected $message = 'Refresh Token not found';
}
