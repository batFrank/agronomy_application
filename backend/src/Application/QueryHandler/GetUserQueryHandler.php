<?php

declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Authorization\User\UserVoter;
use App\Application\Hydrator\User\UserHydratorCollection;
use App\Application\Query\GetUser;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Hydrator\HydratorException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCollection;
use App\Domain\User\UserHydrationCriteria;
use App\Domain\User\UserRepository;

readonly class GetUserQueryHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserHydratorCollection $userHydratorCollection,
    ) {
    }

    /**
     * @throws InfrastructureException
     * @throws HydratorException
     * @throws UserNotFoundException
     * @throws UserForbiddenException
     */
    public function handle(GetUser $query): User
    {
        $targetUser = $this->userRepository->findOneBy($query->getCriteria());

        $requestingUser = $query->getRequestingUser();

        if (null !== $requestingUser && !UserVoter::canView($targetUser, $requestingUser)) {
            throw UserForbiddenException::cannotViewUser();
        }

        if ($query->needsCreator()) {
            $this->userHydratorCollection->hydrate(
                new UserCollection([$targetUser]),
                UserHydrationCriteria::createEmpty()->withCreator()
            );
        }

        return $targetUser;
    }
}
