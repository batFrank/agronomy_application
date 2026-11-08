<?php

declare(strict_types=1);

namespace App\Tests\Domain\OTP\DeliveryChannel;

use App\Domain\OTP\DeliveryChannel\DeliveryChannelDispatcher;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelException;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelInterface;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelValue;
use App\Domain\OTP\DeliveryChannel\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliveryChannelDispatcher::class)]
class DeliveryChannelDispatcherTest extends TestCase
{
    /**
     * @throws DeliveryChannelException
     */
    public function testDispatchWhenTypeIsNotSupported(): void
    {
        $channel1 = $this->createMock(DeliveryChannelInterface::class);
        $channel2 = $this->createMock(DeliveryChannelInterface::class);
        $channel1->expects($this->once())->method('supports')->willReturn(false);
        $channel2->expects($this->once())->method('supports')->willReturn(false);

        $channel1->expects($this->never())->method('send');
        $channel2->expects($this->never())->method('send');

        $this->expectExceptionObject(DeliveryChannelException::unsupportedType(Type::SMS));
        $this->getDispatcher([$channel1, $channel2])
            ->dispatch(
                new DeliveryChannelValue(
                    destination: 'destination',
                    type: Type::SMS,
                    value: 'value',
                )
            );
    }

    /**
     * @throws DeliveryChannelException
     */
    public function testDispatchWhenTypeIsSupported(): void
    {
        $channel1 = $this->createMock(DeliveryChannelInterface::class);
        $channel2 = $this->createMock(DeliveryChannelInterface::class);
        $channel1->expects($this->once())->method('supports')->willReturn(true);
        $channel2->expects($this->never())->method('supports');

        $channel1->expects($this->once())->method('send');
        $channel2->expects($this->never())->method('send');

        $this->getDispatcher([$channel1, $channel2])
            ->dispatch(
                new DeliveryChannelValue(
                    destination: 'destination',
                    type: Type::SMS,
                    value: 'value',
                )
            );
    }

    /**
     * @param iterable<DeliveryChannelInterface> $deliveryChannels
     */
    private function getDispatcher(iterable $deliveryChannels): DeliveryChannelDispatcher
    {
        return new DeliveryChannelDispatcher(
            deliveryChannels: $deliveryChannels
        );
    }
}
