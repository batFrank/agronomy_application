<?php

declare(strict_types=1);

namespace App\Domain\OTP;

enum Purpose
{
    case Login2FA;
    case EmailVerification;
    case PasswordReset;
}
