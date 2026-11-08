<?php

declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Hydrator\User\UserHydratorCollection;
use App\Application\Query\GetUsers;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Hydrator\HydratorException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Domain\User\PaginatedUserCollection;
use App\Domain\User\User;
use App\Domain\User\UserHydrationCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;

readonly class GetUsersQueryHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserHydratorCollection $userHydratorCollection,
    ) {
    }

    /**
     * @throws InfrastructureException
     * @throws HydratorException
     * @throws UserForbiddenException
     */
    public function handle(GetUsers $query): PaginatedUserCollection
    {
        $criteria = $query->getCriteria();
        $requestingUser = $query->getRequestingUser();

        // Auto-scope for managers: only see users they created
        if (
            $requestingUser instanceof User
            && $requestingUser->hasRole(UserRole::Manager)
            && !$requestingUser->hasRole(UserRole::Admin)
        ) {
            if ($criteria->getCreatedBy() && !$requestingUser->getId()->equals($criteria->getCreatedBy())) {
                throw UserForbiddenException::managerOnlyAllowedToSeeOwnUsers();
            }

            $criteria = $criteria->filterByCreatedBy($requestingUser->getId());
        }

        $users = $this->userRepository->findBy($criteria);
        $total = $this->userRepository->countBy($criteria);

        if ($query->needsCreator()) {
            $this->userHydratorCollection->hydrate(
                $users,
                UserHydrationCriteria::createEmpty()->withCreator()
            );
        }

        return new PaginatedUserCollection($users, $total);
    }
}
