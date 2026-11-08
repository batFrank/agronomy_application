<?php

declare(strict_types=1);

namespace App\Domain\OTP\Provider;

use App\Domain\OTP\DeliveryChannel\DeliveryChannelValue;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Resource\Resource;

final readonly class Request
{
    /**
     * @param int $ttl Time in seconds
     */
    public function __construct(
        private Resource $resource,
        private Purpose $purpose,
        private int $codeLength = 6,
        private int $ttl = 60,
        private ?DeliveryChannelValue $deliveryChannel = null,
    ) {
    }

    public function getResource(): Resource
    {
        return $this->resource;
    }

    public function getPurpose(): Purpose
    {
        return $this->purpose;
    }

    public function getCodeLength(): int
    {
        return $this->codeLength;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function getDeliveryChannel(): ?DeliveryChannelValue
    {
        return $this->deliveryChannel;
    }
}
