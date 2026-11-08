<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\User as DomainUser;
use App\Domain\User\UserRole;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private ?DomainUser $user = null;

    private const array ROLE_MAP = [
        'ROLE_USER' => UserRole::User,
        'ROLE_MANAGER' => UserRole::Manager,
        'ROLE_ADMIN' => UserRole::Admin,
    ];

    /**
     * @var array<string>
     */
    private array $roles;

    /**
     * @param array<UserRole|string> $roles
     *
     * @throws SecurityException
     */
    public function __construct(
        private readonly Uuid $id,
        private readonly Email $email,
        array $roles,
        private readonly ?string $passwordHash = null,
    ) {
        $this->roles = array_map(
            fn (UserRole|string $role) => $role instanceof UserRole ? self::fromUserRoleToString($role) : $role,
            $roles
        );
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return non-empty-string
     *
     * @throws SecurityException
     */
    public function getUserIdentifier(): string
    {
        // Defensive guard: Email validates on construction and is final, so an empty value cannot be built
        // today. It stays because it is what makes the return type non-empty-string true.
        if ('' === $this->email->value()) {
            throw SecurityException::invalidUserIdentifier();
        }

        return $this->email->value();
    }

    public function getUser(): ?DomainUser
    {
        return $this->user;
    }

    public function setUser(?DomainUser $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
        // Not required
    }

    /**
     * @throws SecurityException
     */
    public static function createFromDomainUser(DomainUser $user): self
    {
        return (new User(
            id: $user->getId(),
            email: $user->getEmail(),
            roles: $user->getRoles(),
            passwordHash: $user->getPasswordHash(),
        ))->setUser($user);
    }

    /**
     * @throws SecurityException
     */
    private static function fromUserRoleToString(UserRole $role): string
    {
        $stringRole = array_search($role, self::ROLE_MAP);

        if (false === $stringRole) {
            throw new SecurityException('Invalid role');
        }

        return $stringRole;
    }

    /**
     * @throws SecurityException
     */
    public function hasRole(UserRole $role): bool
    {
        return \in_array(self::fromUserRoleToString($role), $this->roles, true);
    }
}
