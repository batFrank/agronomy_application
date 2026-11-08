<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Bus\Command;

readonly class RecoverUserPassword implements Command
{
    public function __construct(
        private string $email,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
