<?php

declare(strict_types=1);

namespace App\Domain\OTP\DeliveryChannel;

enum Type
{
    case Email;
    case SMS;
}
