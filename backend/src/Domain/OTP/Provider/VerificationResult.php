<?php

declare(strict_types=1);

namespace App\Domain\OTP\Provider;

enum VerificationResult
{
    case Valid;
    case Expired;
    case NotFound;
    case MaxAttemptsReached;
    case InvalidCode;
}
