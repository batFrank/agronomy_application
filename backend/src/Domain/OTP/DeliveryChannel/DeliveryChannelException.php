<?php

declare(strict_types=1);

namespace App\Domain\OTP\DeliveryChannel;

use App\Domain\Shared\Exception\InfrastructureException;

final class DeliveryChannelException extends InfrastructureException
{
    /**
     * @param non-empty-string|class-string $current
     * @param non-empty-string|class-string $expected
     */
    public static function unsupportedDestinationType(string $current, string $expected): self
    {
        return new self(message: \sprintf('Unsupported destination type: %s. Expected: %s', $current, $expected));
    }

    public static function unsupportedType(Type $type): self
    {
        return new self(message: \sprintf('Unsupported type: %s', $type->name));
    }

    public static function valueCannotBeNull(): self
    {
        return new self(message: 'Value cannot be null');
    }
}
