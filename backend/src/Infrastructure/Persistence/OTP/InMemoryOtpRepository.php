<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OTP;

use App\Domain\OTP\Exception\OtpNotFoundException;
use App\Domain\OTP\Otp;
use App\Domain\OTP\OtpCollection;
use App\Domain\OTP\OtpCriteria;
use App\Domain\OTP\OtpRepository;
use App\Domain\Shared\Collection\CollectionException;
use App\Infrastructure\Persistence\InMemoryCommonCriteriaBuilder;

class InMemoryOtpRepository implements OtpRepository
{
    private OtpCollection $collection;

    /**
     * @throws CollectionException
     */
    public function __construct()
    {
        $this->collection = new OtpCollection([]);
    }

    #[\Override]
    public function put(Otp $otp): void
    {
        $items = $this->collection->all();
        $items[$otp->getId()->value()] = clone $otp;
        $this->collection->replace($items);
    }

    #[\Override]
    public function findOneBy(OtpCriteria $criteria): Otp
    {
        $collection = $this->filterByCriteria($criteria);

        return $collection->first() ?? throw new OtpNotFoundException();
    }

    #[\Override]
    public function findBy(OtpCriteria $criteria): OtpCollection
    {
        return $this->filterByCriteria($criteria);
    }

    #[\Override]
    public function delete(Otp $otp): void
    {
        $items = $this->collection->all();
        unset($items[$otp->getId()->value()]);
        $this->collection->replace($items);
    }

    /**
     * @throws CollectionException
     */
    private function filterByCriteria(OtpCriteria $criteria): OtpCollection
    {
        $collection = $this->collection->filter(
            fn (Otp $otp) => (null === $criteria->getResource() || $otp->getResource()->equals($criteria->getResource()))
                && (null === $criteria->getPurpose() || $otp->getPurpose() === $criteria->getPurpose())
                && (null === $criteria->getCodeSecret() || $otp->getCodeSecret() === $criteria->getCodeSecret())
                && (null === $criteria->getRevoked() || $otp->isRevoked() === $criteria->getRevoked())
                && (
                    null === $criteria->getConsumedAt()
                    || InMemoryCommonCriteriaBuilder::validateCondition($otp->getConsumedAt(), $criteria->getConsumedAt())
                )
        );

        /** @var OtpCollection $collection */
        $collection = InMemoryCommonCriteriaBuilder::filterByCommonCriteria(
            criteria: $criteria,
            collection: $collection,
        );

        return $collection->map(
            fn (Otp $otp) => new Otp(
                id: $otp->getId(),
                resource: $otp->getResource(),
                purpose: $otp->getPurpose(),
                codeSecret: $otp->getCodeSecret(),
                attempts: $otp->getAttempts(),
                maxAttempts: $otp->getMaxAttempts(),
                revoked: $otp->isRevoked(),
                expiresAt: $otp->getExpiresAt(),
                consumedAt: $otp->getConsumedAt(),
                createdAt: $otp->getCreatedAt(),
            )
        );
    }
}
