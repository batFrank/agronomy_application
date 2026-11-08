<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Security\Exceptions\RefreshTokenNotFoundException;
use App\Domain\Shared\Exception\InfrastructureException;

interface RefreshTokenRepository
{
    /**
     * @throws InfrastructureException
     */
    public function put(RefreshToken $token): void;

    /**
     * @throws InfrastructureException
     * @throws RefreshTokenNotFoundException
     */
    public function findOneBy(RefreshTokenCriteria $criteria): RefreshToken;

    public function findBy(RefreshTokenCriteria $criteria): RefreshTokenCollection;

    public function delete(RefreshToken $refreshToken): void;
}
