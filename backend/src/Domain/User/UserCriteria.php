<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Shared\Criteria\CriteriaWithId;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Uuid\Uuid;

/**
 * @extends CriteriaWithId<UserCriteria>
 */
class UserCriteria extends CriteriaWithId
{
    private ?string $searchString = null;
    private ?Email $email = null;
    private ?UserRole $role = null;
    /** @var UserRole[]|null */
    private ?array $roles = null;
    private ?string $passwordResetToken = null;
    private ?\DateTimeImmutable $passwordResetTokenExpiresBefore = null;
    private ?Uuid $createdBy = null;

    public function isEmpty(): bool
    {
        return parent::isEmpty()
            && null === $this->searchString
            && null === $this->role
            && null === $this->roles
            && null === $this->passwordResetToken
            && null === $this->passwordResetTokenExpiresBefore
            && null === $this->createdBy;
    }

    public function filterByEmail(Email $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): ?Email
    {
        return $this->email;
    }

    public function filterBySearchString(string $searchString): self
    {
        $this->searchString = $searchString;

        return $this;
    }

    public function getSearchString(): ?string
    {
        return $this->searchString;
    }

    public function filterByRole(UserRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getRole(): ?UserRole
    {
        return $this->role;
    }

    /**
     * @param UserRole[] $roles
     */
    public function filterByRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @return UserRole[]|null
     */
    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function filterByPasswordResetToken(string $passwordResetToken): self
    {
        $this->passwordResetToken = $passwordResetToken;

        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function filterByPasswordResetTokenExpiresBefore(\DateTimeImmutable $passwordResetTokenExpiration): self
    {
        $this->passwordResetTokenExpiresBefore = $passwordResetTokenExpiration;

        return $this;
    }

    public function getPasswordResetTokenExpiresBefore(): ?\DateTimeImmutable
    {
        return $this->passwordResetTokenExpiresBefore;
    }

    public function filterByCreatedBy(Uuid $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedBy(): ?Uuid
    {
        return $this->createdBy;
    }
}
