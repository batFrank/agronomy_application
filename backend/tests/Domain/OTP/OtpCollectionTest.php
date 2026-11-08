<?php

declare(strict_types=1);

namespace App\Tests\Domain\OTP;

use App\Domain\OTP\Otp;
use App\Domain\OTP\OtpCollection;
use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Tests\Domain\Shared\Entity\EntityWithIdCollectionTestCase;
use App\Tests\Mother\Domain\OTP\OtpMother;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends EntityWithIdCollectionTestCase<Otp>
 */
#[CoversClass(OtpCollection::class)]
class OtpCollectionTest extends EntityWithIdCollectionTestCase
{
    /**
     * @param array<int, Otp> $items
     *
     * @return Collection<object>
     *
     * @throws CollectionException
     */
    protected function createCollection(array $items): Collection
    {
        return new OtpCollection($items); // @phpstan-ignore return.type
    }

    protected function getExpectedType(): string
    {
        return Otp::class;
    }

    protected function getItem(): object
    {
        return OtpMother::create();
    }
}
