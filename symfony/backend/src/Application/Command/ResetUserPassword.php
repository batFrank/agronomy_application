<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Bus\Command;

readonly class ResetUserPassword implements Command
{
    public function __construct(
        private string $resetToken,
        private string $newPassword,
    ) {
    }

    public function getResetToken(): string
    {
        return $this->resetToken;
    }

    public function getNewPassword(): string
    {
        return $this->newPassword;
    }
}
