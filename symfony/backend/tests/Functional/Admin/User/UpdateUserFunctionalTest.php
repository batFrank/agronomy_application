<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin\User;

use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\UserRole;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\HelperTrait\Endpoints\Admin\UserEndpoints;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserFunctionalTest extends FunctionalTestCase
{
    public function testUpdateUserWithoutAuthentication(): void
    {
        $response = $this->makeRequest(
            method: 'PATCH',
            uri: UserEndpoints::updateUserEndpoint(self::DEFAULT_USER_ID),
            body: [
                'email' => 'updated@example.com',
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
    public function testUpdateUserWithNonAdminUser(): void
    {
        $user = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'regular.user@example.com',
        );

        $token = $this->loginAndGetTokenForUser($user);

        $response = $this->makeRequest(
            method: 'PATCH',
            uri: UserEndpoints::updateUserEndpoint($user->getId()->value()),
            body: [
                'email' => 'updated@example.com',
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
    public function testUpdateUserEmailSuccessfully(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $userToUpdate = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'user.to.update@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'PATCH',
            uri: UserEndpoints::updateUserEndpoint($userToUpdate->getId()->value()),
            body: [
                'email' => 'newemail@example.com',
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $userData = $this->extractResponseData($response);
        $this->assertEquals('newemail@example.com', $userData['email']);
        $this->assertArrayHasKey('updated_at', $userData);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testUpdateUserRolesSuccessfully(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $userToUpdate = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'user.to.update@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'PATCH',
            uri: UserEndpoints::updateUserEndpoint($userToUpdate->getId()->value()),
            body: [
                'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

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
    public function testUpdateUserPasswordSuccessfully(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $userToUpdate = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'user.to.update@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'PATCH',
            uri: UserEndpoints::updateUserEndpoint($userToUpdate->getId()->value()),
            body: [
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testUpdateUserWithInvalidEmail(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $userToUpdate = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'user.to.update@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'PATCH',
            uri: UserEndpoints::updateUserEndpoint($userToUpdate->getId()->value()),
            body: [
                'email' => 'invalid-email',
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
    public function testUpdateUserWithPasswordMismatch(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $userToUpdate = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'user.to.update@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'PATCH',
            uri: UserEndpoints::updateUserEndpoint($userToUpdate->getId()->value()),
            body: [
                'password' => 'newpassword123',
                'password_confirmation' => 'differentpassword',
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
    public function testUpdateNonExistentUser(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'PATCH',
            uri: UserEndpoints::updateUserEndpoint('00000000-0000-0000-0000-000000000000'),
            body: [
                'email' => 'newemail@example.com',
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertContains($response->getStatusCode(), [Response::HTTP_NOT_FOUND, Response::HTTP_BAD_REQUEST]);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testUpdateUserWithInvalidUuid(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'PATCH',
            uri: UserEndpoints::updateUserEndpoint('invalid-uuid'),
            body: [
                'email' => 'newemail@example.com',
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
