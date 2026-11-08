<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Shared\Email;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;

class EmailMother
{
    private const string DEFAULT_EMAIL = 'user@email.com';

    /**
     * @throws InvalidEmailException
     */
    public static function create(?string $email = null): Email
    {
        return new Email($email ?? self::DEFAULT_EMAIL);
    }
}
