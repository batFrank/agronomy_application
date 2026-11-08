<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Security\User as SecurityUser;
use App\Domain\Bus\Command;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\UserRole;

readonly class UpdateUser implements Command
{
    /**
     * @param UserRole[]|null $roles
     */
    public function __construct(
        private Uuid $id,
        private SecurityUser $requestingUser,
        private ?Email $email = null,
        private ?string $password = null,
        private ?array $roles = null,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?Email
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @return UserRole[]|null
     */
    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function getRequestingUser(): SecurityUser
    {
        return $this->requestingUser;
    }
}
