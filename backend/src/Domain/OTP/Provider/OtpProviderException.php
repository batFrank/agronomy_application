<?php

declare(strict_types=1);

namespace App\Domain\OTP\Provider;

use App\Domain\Shared\Exception\InfrastructureException;

class OtpProviderException extends InfrastructureException
{
    public static function deliveryChannelCannotBeNull(): self
    {
        return new self('Delivery channel cannot be null');
    }
}
