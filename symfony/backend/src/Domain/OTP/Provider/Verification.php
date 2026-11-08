<?php

declare(strict_types=1);

namespace App\Domain\OTP\Provider;

use App\Domain\OTP\Purpose;
use App\Domain\Shared\Resource\Resource;

final readonly class Verification
{
    /**
     * @param string $code Decoded value
     */
    public function __construct(
        private Resource $resource,
        private Purpose $purpose,
        #[\SensitiveParameter]
        private string $code,
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

    public function getCode(): string
    {
        return $this->code;
    }
}
