<?php

declare(strict_types=1);

namespace App\Domain\OTP\DeliveryChannel;

interface DeliveryChannelInterface
{
    public function supports(Type $type): bool;

    /**
     * @throws DeliveryChannelException
     */
    public function send(DeliveryChannelValue $value): void;
}
