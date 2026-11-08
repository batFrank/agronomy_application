<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\User;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\Exception\EmailAlreadyInUseException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Exception\UserRepositoryException;
use App\Domain\User\User;
use App\Domain\User\UserCollection;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Infrastructure\Persistence\DBALCommonCriteriaBuilder;
use App\Infrastructure\Persistence\DBALDateTimeFormatter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query\QueryBuilder;

readonly class DBALUserRepository implements UserRepository
{
    private const array ROLE_MAP = [
        'ROLE_USER' => UserRole::User,
        'ROLE_MANAGER' => UserRole::Manager,
        'ROLE_ADMIN' => UserRole::Admin,
    ];

    public function __construct(
        private Connection $connection,
        private string $userTableName,
    ) {
    }

    /**
     * @throws InfrastructureException
     * @throws EmailAlreadyInUseException
     */
    #[\Override]
    public function put(User $user): void
    {
        try {
            $this->findOneBy(UserCriteria::createById($user->getId()));

            $this->update($user);
        } catch (UserNotFoundException) {
            $this->insert($user);
        }
    }

    #[\Override]
    public function findOneBy(UserCriteria $criteria): User
    {
        try {
            $userArray = $this
                ->getQueryBuilderByCriteria($criteria)
                ->executeQuery()
                ->fetchAssociative();

            if (false === $userArray) {
                throw new UserNotFoundException();
            }

            return $this->fromArrayToUser($userArray);
        } catch (DBALException|InvalidUuidException|InvalidEmailException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    #[\Override]
    public function findBy(UserCriteria $criteria): UserCollection
    {
        try {
            $usersArray = $this
                ->getQueryBuilderByCriteria($criteria)
                ->executeQuery()
                ->fetchAllAssociative();

            return new UserCollection(
                array_map(
                    fn (array $userArray) => $this->fromArrayToUser($userArray),
                    $usersArray
                )
            );
        } catch (DBALException|InvalidUuidException|InvalidEmailException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    #[\Override]
    public function countBy(UserCriteria $criteria): int
    {
        try {
            return $this
                ->getQueryBuilderByCriteria(
                    criteria: $criteria,
                    onlyFilter: true,
                )
                ->select('COUNT(t.id)')
                ->executeQuery()
                ->fetchOne();
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @throws EmailAlreadyInUseException
     * @throws UserRepositoryException
     */
    private function insert(User $user): void
    {
        try {
            $this->connection->insert(
                table: $this->userTableName,
                data: $this->fromUserToArray($user)
            );
        } catch (UniqueConstraintViolationException) {
            throw EmailAlreadyInUseException::create();
        } catch (DBALException $e) {
            throw UserRepositoryException::fromPrevious($e);
        }
    }

    /**
     * @throws EmailAlreadyInUseException
     * @throws UserRepositoryException
     */
    private function update(User $user): void
    {
        try {
            $this->connection->update(
                table: $this->userTableName,
                data: $this->fromUserToArray($user),
                criteria: ['id' => $user->getId()->value()]
            );
        } catch (UniqueConstraintViolationException) {
            throw EmailAlreadyInUseException::create();
        } catch (DBALException $e) {
            throw UserRepositoryException::fromPrevious($e);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UserRepositoryException
     */
    private function fromUserToArray(User $user): array
    {
        return [
            'id' => $user->getId()->value(),
            'email' => $user->getEmail()->value(),
            'roles' => json_encode(array_map(fn ($role) => $this->fromUserRoleToString($role), $user->getRoles())),
            'password_hash' => $user->getPasswordHash(),
            'created_at' => DBALDateTimeFormatter::format($user->getCreatedAt()),
            'updated_at' => DBALDateTimeFormatter::format($user->getUpdatedAt()),
            'deleted_at' => DBALDateTimeFormatter::format($user->getDeletedAt()),
            'password_reset_token' => $user->getPasswordResetToken(),
            'password_reset_token_expiration' => DBALDateTimeFormatter::format($user->getPasswordResetTokenExpiration()),
            'created_by' => $user->getCreatedBy()?->value(),
        ];
    }

    /**
     * @param array<string, mixed> $userArray
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws UserRepositoryException
     */
    private function fromArrayToUser(array $userArray): User
    {
        // Decode roles from JSON field
        $rolesJson = $userArray['roles'] ?? '[]';
        $roleStrings = json_decode($rolesJson, true);

        if (!\is_array($roleStrings)) {
            $roleStrings = [];
        }

        $roles = array_map(
            fn (string $roleString) => self::ROLE_MAP[$roleString] ?? UserRole::User,
            $roleStrings
        );

        // If no roles found, assign default User role
        if (empty($roles)) {
            $roles = [UserRole::User];
        }

        $createdAt = DBALDateTimeFormatter::parse($userArray['created_at']);
        if (null === $createdAt) {
            throw new UserRepositoryException('Invalid created_at format');
        }

        return new User(
            id: new Uuid($userArray['id']),
            email: new Email($userArray['email']),
            roles: $roles,
            passwordHash: $userArray['password_hash'],
            createdAt: $createdAt,
            updatedAt: DBALDateTimeFormatter::parse($userArray['updated_at']),
            deletedAt: DBALDateTimeFormatter::parse($userArray['deleted_at']),
            passwordResetToken: $userArray['password_reset_token'],
            passwordResetTokenExpiration: DBALDateTimeFormatter::parse($userArray['password_reset_token_expiration']),
            createdBy: $userArray['created_by'] ? new Uuid($userArray['created_by']) : null,
        );
    }

    /**
     * @throws UserRepositoryException
     */
    private function getQueryBuilderByCriteria(
        UserCriteria $criteria,
        bool $onlyFilter = false,
    ): QueryBuilder {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->select('t.*')
            ->from($this->userTableName, 't')
            ->andWhere('t.deleted_at IS NULL');

        if (null !== $criteria->getEmail()) {
            $queryBuilder
                ->andWhere('t.email = :email')
                ->setParameter('email', $criteria->getEmail()->value());
        }

        if (null !== $criteria->getRole()) {
            $queryBuilder
                ->andWhere('t.roles @> :role')
                ->setParameter('role', json_encode([$this->fromUserRoleToString($criteria->getRole())]));
        }

        if (null !== $criteria->getRoles()) {
            $conditions = [];
            foreach ($criteria->getRoles() as $index => $role) {
                $paramName = 'role_' . $index;
                $conditions[] = "t.roles @> :$paramName";
                $queryBuilder->setParameter($paramName, json_encode([$this->fromUserRoleToString($role)]));
            }
            if (!empty($conditions)) {
                $queryBuilder->andWhere('(' . implode(' OR ', $conditions) . ')');
            }
        }

        if (null !== $criteria->getSearchString()) {
            $queryBuilder
                ->andWhere('(t.email LIKE :search)')
                ->setParameter('search', '%' . $criteria->getSearchString() . '%');
        }

        if (null !== $criteria->getPasswordResetToken()) {
            $queryBuilder
                ->andWhere('t.password_reset_token = :passwordResetToken')
                ->setParameter('passwordResetToken', $criteria->getPasswordResetToken());
        }

        if (null !== $criteria->getPasswordResetTokenExpiresBefore()) {
            $queryBuilder
                ->andWhere('t.password_reset_token_expiration > :passwordResetTokenExpiresBefore')
                ->setParameter('passwordResetTokenExpiresBefore', DBALDateTimeFormatter::format($criteria->getPasswordResetTokenExpiresBefore()));
        }

        if (null !== $criteria->getCreatedBy()) {
            $queryBuilder
                ->andWhere('t.created_by = :createdBy')
                ->setParameter('createdBy', $criteria->getCreatedBy()->value());
        }

        DBALCommonCriteriaBuilder::filterByCommonCriteria($criteria, $queryBuilder, $onlyFilter);

        return $queryBuilder;
    }

    /**
     * @throws UserRepositoryException
     */
    private function fromUserRoleToString(UserRole $role): string
    {
        $stringRole = array_search($role, self::ROLE_MAP);

        if (false === $stringRole) {
            throw new UserRepositoryException('Invalid role');
        }

        return $stringRole;
    }
}
