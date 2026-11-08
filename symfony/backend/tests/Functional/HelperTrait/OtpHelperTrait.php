<?php

declare(strict_types=1);

namespace App\Tests\Functional\HelperTrait;

use App\Domain\OTP\Otp;
use App\Domain\OTP\OtpRepository;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\OTP\OtpMother;

trait OtpHelperTrait
{
    protected function getOtpRepository(): object
    {
        return $this->getContainer()->get(OtpRepository::class);
    }

    protected function setAndGetOtpInRepository(
        ?Uuid $id = null,
        ?Resource $resource = null,
        Purpose $purpose = Purpose::Login2FA,
        ?string $codeSecret = null,
        int $attempts = 1,
        int $maxAttempts = 1,
        bool $revoked = false,
        \DateTimeImmutable $expiresAt = new \DateTimeImmutable('+1 hour'),
        ?\DateTimeImmutable $consumedAt = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): Otp {
        $otp = OtpMother::create(
            id: $id,
            resource: $resource,
            purpose: $purpose,
            codeSecret: $codeSecret,
            attempts: $attempts,
            maxAttempts: $maxAttempts,
            revoked: $revoked,
            expiresAt: $expiresAt,
            consumedAt: $consumedAt,
            createdAt: $createdAt,
        );

        /** @var OtpRepository $repository */
        $repository = $this->getOtpRepository();
        $repository->put($otp);

        return $otp;
    }
}
