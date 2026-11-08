<?php

declare(strict_types=1);

namespace App\Domain\OTP\DeliveryChannel;

/**
 * All validation must be made inside the delivery channel.
 * This dispatcher is only responsible for selecting the right channel.
 */
class DeliveryChannelDispatcher
{
    /**
     * @param iterable<DeliveryChannelInterface> $deliveryChannels
     */
    public function __construct(
        private readonly iterable $deliveryChannels,
    ) {
    }

    /**
     * @throws DeliveryChannelException
     */
    public function dispatch(DeliveryChannelValue $deliveryChannelValue): void
    {
        foreach ($this->deliveryChannels as $deliveryChannel) {
            if ($deliveryChannel->supports($deliveryChannelValue->getType())) {
                $deliveryChannel->send($deliveryChannelValue);

                return;
            }
        }

        throw DeliveryChannelException::unsupportedType($deliveryChannelValue->getType());
    }
}
