<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\OTP;

use App\Domain\OTP\OtpRepository;
use App\Infrastructure\Persistence\OTP\InMemoryOtpRepository;
use App\Tests\Domain\OTP\OtpRepositoryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryOtpRepository::class)]
class InMemoryOtpRepositoryTest extends OtpRepositoryTestCase
{
    protected function getRepository(): OtpRepository
    {
        return new InMemoryOtpRepository();
    }
}
