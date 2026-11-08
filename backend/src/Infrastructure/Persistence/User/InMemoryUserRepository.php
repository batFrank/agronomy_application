<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\User;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCollection;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Infrastructure\Persistence\InMemoryCommonCriteriaBuilder;

class InMemoryUserRepository implements UserRepository
{
    private UserCollection $collection;

    /**
     * @throws CollectionException
     */
    public function __construct()
    {
        $this->collection = new UserCollection([]);
    }

    #[\Override]
    public function put(User $user): void
    {
        $this->collection = $this->collection->filter(
            fn (User $repositoryUser): bool => !$repositoryUser->getId()->equals($user->getId())
        );
        $this->collection->append($user);
    }

    #[\Override]
    public function findOneBy(UserCriteria $criteria): User
    {
        $users = $this->filterByCriteria($criteria);

        if (null === $users->first()) {
            throw new UserNotFoundException();
        }

        return $users->first();
    }

    #[\Override]
    public function findBy(UserCriteria $criteria): UserCollection
    {
        return $this->filterByCriteria($criteria);
    }

    #[\Override]
    public function countBy(UserCriteria $criteria): int
    {
        return $this->filterByCriteria(
            criteria: $criteria,
            onlyFilter: true,
        )->count();
    }

    /**
     * @throws CollectionException
     */
    private function filterByCriteria(UserCriteria $criteria, bool $onlyFilter = false): UserCollection
    {
        $collection = $this->collection->filter(
            fn (User $user): bool => (
                null === $user->getDeletedAt()
            ) && (
                null === $criteria->getEmail() || $user->getEmail()->equals($criteria->getEmail())
            )
                && (
                    null === $criteria->getRole() || $user->hasRole($criteria->getRole())
                ) && (
                    null === $criteria->getSearchString() || str_contains($user->getEmail()->value(), $criteria->getSearchString())
                ) && (
                    null === $criteria->getRoles() || !empty(
                        array_intersect(
                            array_map(fn (UserRole $role) => $role->name, $criteria->getRoles()),
                            array_map(fn (UserRole $role) => $role->name, $user->getRoles())
                        )
                    )
                ) && (
                    null === $criteria->getPasswordResetToken() || $user->getPasswordResetToken() === $criteria->getPasswordResetToken()
                ) && (
                    null === $criteria->getPasswordResetTokenExpiresBefore() || $user->getPasswordResetTokenExpiration() > $criteria->getPasswordResetTokenExpiresBefore()
                ) && (
                    null === $criteria->getCreatedBy() || ($user->getCreatedBy() && $user->getCreatedBy()->equals($criteria->getCreatedBy()))
                )
        );

        $collection = InMemoryCommonCriteriaBuilder::filterByCommonCriteria(
            criteria: $criteria,
            collection: $collection,
            onlyFilter: $onlyFilter,
        );

        return $collection->map(
            fn (User $user) => new User(
                id: $user->getId(),
                email: $user->getEmail(),
                roles: $user->getRoles(),
                passwordHash: $user->getPasswordHash(),
                createdAt: $user->getCreatedAt(),
                updatedAt: $user->getUpdatedAt(),
                deletedAt: $user->getDeletedAt(),
                passwordResetToken: $user->getPasswordResetToken(),
                passwordResetTokenExpiration: $user->getPasswordResetTokenExpiration(),
                createdBy: $user->getCreatedBy(),
            )
        );
    }
}
