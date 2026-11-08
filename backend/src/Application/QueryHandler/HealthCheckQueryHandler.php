<?php

declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Query\HealthCheckQuery;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;

readonly class HealthCheckQueryHandler
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    /**
     * @throws InfrastructureException
     */
    public function handle(HealthCheckQuery $query): void
    {
        $userCriteria = UserCriteria::createEmpty()->filterByEmail(
            new Email('soporte@grupogestionet.com')
        );

        $this->userRepository->countBy($userCriteria);
    }
}
