<?php

declare(strict_types=1);

namespace App\Domain\OTP;

use App\Domain\Shared\Entity\EntityWithIdCollection;

/**
 * @extends EntityWithIdCollection<Otp>
 */
class OtpCollection extends EntityWithIdCollection
{
    public function getTypeName(): string
    {
        return Otp::class;
    }
}
