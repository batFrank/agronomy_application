<?php

declare(strict_types=1);

namespace App\Tests\Functional\HelperTrait;

use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Tests\Functional\HelperTrait\Endpoints\AuthEndpoints;
use App\Tests\Mother\Domain\User\UserMother;
use Symfony\Component\HttpFoundation\Response;

trait UserHelperTrait
{
    protected const array DEFAULT_ROLES = [UserRole::User];

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     */
    protected function getDefaultUser(): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->getContainer()->get('App\Domain\User\UserRepository');

        return $userRepository->findOneBy(
            UserCriteria::createById(new Uuid(self::DEFAULT_USER_ID))
        );
    }

    protected function loginAndGetToken(
        string $email = self::DEFAULT_USER_EMAIL,
        string $password = self::DEFAULT_USER_PASSWORD,
    ): string {
        $response = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::loginEndpoint(),
            body: [
                'email' => $email,
                'password' => $password,
            ],
            headers: ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->extractResponseData($response);

        $this->assertArrayHasKey('token', $responseData);

        return $responseData['token'];
    }

    protected function loginAndGetTokenForUser(User $user): string
    {
        return $this->loginAndGetToken($user->getEmail()->value(), self::DEFAULT_USER_PASSWORD);
    }

    /**
     * @param array<UserRole> $roles
     *
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     * @throws InfrastructureException
     */
    protected function createAndGetUser(
        array $roles = self::DEFAULT_ROLES,
        string $email = 'john.doe@mail.com',
        string $password = self::DEFAULT_USER_PASSWORD,
        ?Uuid $id = null,
        ?Uuid $createdBy = null,
    ): User {
        $user = UserMother::create(
            id: $id,
            email: new Email($email),
            roles: $roles,
            passwordHash: $this->hashPassword($email, $roles, $password),
            createdBy: $createdBy,
        );

        $this->getUserRepository()->put($user);

        return $user;
    }
}
