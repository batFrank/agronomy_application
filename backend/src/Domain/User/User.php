<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Entity\LifeCycleEntity;
use App\Domain\Shared\UserCard\UserCard;
use App\Domain\Shared\Uuid\Uuid;

class User extends LifeCycleEntity
{
    private ?UserCard $creator = null;

    /**
     * @param UserRole[] $roles
     */
    public function __construct(
        Uuid $id,
        private readonly Email $email,
        private readonly array $roles,
        private readonly string $passwordHash,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null,
        private ?string $passwordResetToken = null,
        private ?\DateTimeImmutable $passwordResetTokenExpiration = null,
        private readonly ?Uuid $createdBy = null,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $deletedAt);
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    /**
     * @return UserRole[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasRole(UserRole $role): bool
    {
        return \in_array($role, $this->roles, true);
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function getPasswordResetTokenExpiration(): ?\DateTimeImmutable
    {
        return $this->passwordResetTokenExpiration;
    }

    public function getCreatedBy(): ?Uuid
    {
        return $this->createdBy;
    }

    public function getCreator(): ?UserCard
    {
        return $this->creator;
    }

    public function setCreator(?UserCard $creator): void
    {
        $this->creator = $creator;
    }

    public function getUserCard(): UserCard
    {
        return new UserCard(
            id: $this->getId(),
            email: $this->email
        );
    }
}
