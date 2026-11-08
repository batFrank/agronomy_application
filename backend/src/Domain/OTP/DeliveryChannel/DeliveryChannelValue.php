<?php

declare(strict_types=1);

namespace App\Domain\OTP\DeliveryChannel;

final readonly class DeliveryChannelValue
{
    public function __construct(
        private mixed $destination,
        private Type $type,
        private ?string $value,
    ) {
    }

    public function getDestination(): mixed
    {
        return $this->destination;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
