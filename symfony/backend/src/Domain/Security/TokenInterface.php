<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Security\Exceptions\InvalidTokenException;
use App\Domain\Security\Exceptions\RefreshTokenNotFoundException;
use App\Domain\Security\Exceptions\TokenInterfaceException;
use App\Domain\Shared\Exception\InfrastructureException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

interface TokenInterface
{
    /**
     * @param array<string, mixed> $extra
     *
     * @throws InfrastructureException
     */
    public function getJwtToken(UserInterface $user, array $extra = []): string;

    /**
     * @throws InfrastructureException
     */
    public function getRefreshToken(UserInterface $user): string;

    /**
     * Try to get the user. If the token is invalid an exception will be thrown.
     *
     * @throws TokenInterfaceException
     * @throws InfrastructureException
     */
    public function getUser(string $token): UserInterface;

    /**
     * Validates a refresh token and returns the username associated with it.
     * The refresh token is deleted after validation (single use).
     *
     * @return string The username associated with the refresh token
     *
     * @throws TokenInterfaceException
     * @throws InfrastructureException
     * @throws InvalidTokenException
     * @throws RefreshTokenNotFoundException
     */
    public function validateRefreshToken(string $token): string;

    public function setTokensCookie(Response $response, UserInterface $user): void;
}
