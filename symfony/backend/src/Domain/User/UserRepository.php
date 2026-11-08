<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\EmailAlreadyInUseException;
use App\Domain\User\Exception\UserNotFoundException;

interface UserRepository
{
    /**
     * @throws InfrastructureException
     * @throws EmailAlreadyInUseException
     */
    public function put(User $user): void;

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     */
    public function findOneBy(UserCriteria $criteria): User;

    /**
     * @throws InfrastructureException
     */
    public function findBy(UserCriteria $criteria): UserCollection;

    /**
     * @throws InfrastructureException
     */
    public function countBy(UserCriteria $criteria): int;
}
