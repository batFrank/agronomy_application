<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Bus\Command;

readonly class CheckPasswordResetToken implements Command
{
    public function __construct(
        private string $resetToken,
    ) {
    }

    public function getResetToken(): string
    {
        return $this->resetToken;
    }
}
