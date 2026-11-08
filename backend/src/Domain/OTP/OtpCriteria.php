<?php

declare(strict_types=1);

namespace App\Domain\OTP;

use App\Domain\Shared\Criteria\Condition\Condition;
use App\Domain\Shared\Criteria\Condition\FieldCondition;
use App\Domain\Shared\Criteria\CriteriaWithId;
use App\Domain\Shared\Resource\Resource;

/**
 * @extends CriteriaWithId<OtpCriteria>
 */
final class OtpCriteria extends CriteriaWithId
{
    private ?Resource $resource = null;
    private ?Purpose $purpose = null;
    private ?string $codeSecret = null;
    private ?bool $revoked = null;
    private ?FieldCondition $consumedAt = null;

    public function isEmpty(): bool
    {
        return parent::isEmpty()
            && null === $this->resource
            && null === $this->purpose
            && null === $this->codeSecret
            && null === $this->revoked;
    }

    public function filterByResource(Resource $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    public function filterByPurpose(Purpose $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getPurpose(): ?Purpose
    {
        return $this->purpose;
    }

    public function filterByCodeSecret(string $codeSecret): self
    {
        $this->codeSecret = $codeSecret;

        return $this;
    }

    public function getCodeSecret(): ?string
    {
        return $this->codeSecret;
    }

    public function filterByRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;

        return $this;
    }

    public function getRevoked(): ?bool
    {
        return $this->revoked;
    }

    public function filterByConsumedAt(?\DateTimeImmutable $value, Condition $condition = Condition::Equal): self
    {
        $this->consumedAt = new FieldCondition(
            value: $value,
            condition: $condition
        );

        return $this;
    }

    public function getConsumedAt(): ?FieldCondition
    {
        return $this->consumedAt;
    }
}
