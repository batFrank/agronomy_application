<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Bus\Command;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\UserRole;

readonly class CreateUser implements Command
{
    /**
     * @param UserRole[] $roles
     */
    public function __construct(
        private Uuid $id,
        private Email $email,
        private string $password,
        private array $roles,
        private ?Uuid $createdBy = null,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return UserRole[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getCreatedBy(): ?Uuid
    {
        return $this->createdBy;
    }
}
