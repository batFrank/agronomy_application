<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin\User;

use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\HelperTrait\Endpoints\Admin\UserEndpoints;
use Symfony\Component\HttpFoundation\Response;

class CreateUserFunctionalTest extends FunctionalTestCase
{
    public function testCreateUserWithoutAuthentication(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: UserEndpoints::createUserEndpoint(),
            body: [
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     * @throws InfrastructureException
     */
    public function testCreateUserWithNonAdminUser(): void
    {
        $user = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'regular.user@example.com',
        );

        $token = $this->loginAndGetTokenForUser($user);

        $response = $this->makeRequest(
            method: 'POST',
            uri: UserEndpoints::createUserEndpoint(),
            body: [
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testCreateUserWithAdminUser(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'POST',
            uri: UserEndpoints::createUserEndpoint(),
            body: [
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $userData = $this->extractResponseData($response);
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('email', $userData);
        $this->assertArrayHasKey('roles', $userData);
        $this->assertArrayHasKey('created_at', $userData);
        $this->assertArrayHasKey('created_by', $userData);
        $this->assertEquals('newuser@example.com', $userData['email']);
        $this->assertContains('ROLE_USER', $userData['roles']);
        $this->assertEquals($adminUser->getId()->value(), $userData['created_by']);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testCreateAdminUser(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'POST',
            uri: UserEndpoints::createUserEndpoint(),
            body: [
                'email' => 'newadmin@example.com',
                'password' => 'adminpassword',
                'password_confirmation' => 'adminpassword',
                'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $userData = $this->extractResponseData($response);
        $this->assertContains('ROLE_ADMIN', $userData['roles']);
        $this->assertContains('ROLE_USER', $userData['roles']);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testCreateUserWithInvalidEmail(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'POST',
            uri: UserEndpoints::createUserEndpoint(),
            body: [
                'email' => 'invalid-email',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testCreateUserWithTooShortPassword(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'POST',
            uri: UserEndpoints::createUserEndpoint(),
            body: [
                'email' => 'newuser@example.com',
                'password' => '12345',
                'password_confirmation' => '12345',
                'roles' => ['ROLE_USER'],
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Password must be at least 6 characters.', json_decode((string) $response->getContent(), true)['message']);

        /** @var UserRepository $userRepository */
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $this->assertSame(
            0,
            $userRepository->countBy(UserCriteria::createEmpty()->filterByEmail(new Email('newuser@example.com'))),
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testCreateUserWithEmailAlreadyInUse(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'POST',
            uri: UserEndpoints::createUserEndpoint(),
            body: [
                'email' => 'admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['ROLE_USER'],
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('Email is already in use.', json_decode((string) $response->getContent(), true)['message']);

        /** @var UserRepository $userRepository */
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $this->assertSame(
            1,
            $userRepository->countBy(UserCriteria::createEmpty()->filterByEmail(new Email('admin@example.com'))),
        );
    }
}
