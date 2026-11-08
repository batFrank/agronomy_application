<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin\User;

use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\User;
use App\Domain\User\UserRole;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\HelperTrait\Endpoints\Admin\UserEndpoints;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class GetUserFunctionalTest extends FunctionalTestCase
{
    /**
     * @throws InvalidUuidException
     */
    public function testGetUserWithoutAuthentication(): void
    {
        $userId = UuidMother::create()->value();

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUserEndpoint($userId),
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     * @throws InfrastructureException
     */
    public function testGetUserWithNonAdminUser(): void
    {
        $user = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'regular.user@example.com',
        );

        $token = $this->loginAndGetTokenForUser($user);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUserEndpoint($user->getId()->value()),
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
    public function testGetUserWithAdminUser(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $targetUser = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'target.user@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUserEndpoint($targetUser->getId()->value()),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $userData = $this->extractResponseData($response);
        $this->assertArrayHasKey('id', $userData);
    }

    /**
     * Guarda del cambio a 422: aquí el id malformado lo rechaza UuidValidator antes de llegar al
     * value object, así que este endpoint sigue devolviendo 400 y su contrato no cambia.
     *
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUserWithMalformedIdIsRejectedBeforeTheValueObject(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUserEndpoint('not-a-uuid'),
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
    public function testGetUserReturnsCorrectStructure(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $targetUser = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'target.user@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUserEndpoint($targetUser->getId()->value()),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $userData = $this->extractResponseData($response);
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('email', $userData);
        $this->assertArrayHasKey('roles', $userData);
        $this->assertArrayHasKey('created_at', $userData);

        $this->assertEquals($targetUser->getId()->value(), $userData['id']);
        $this->assertEquals($targetUser->getEmail()->value(), $userData['email']);
        $this->assertContains('ROLE_USER', $userData['roles']);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUserReturnsCreatorWhenHydratedByAdminController(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $creatorUser = $this->createAndGetUser(
            roles: [UserRole::Manager, UserRole::User],
            email: 'creator@example.com',
        );

        $targetUser = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'target.user@example.com',
            createdBy: $creatorUser->getId(),
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUserEndpoint($targetUser->getId()->value()),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $userData = $this->extractResponseData($response);

        $this->assertSame($creatorUser->getId()->value(), $userData['created_by']);
        $this->assertSame([
            'id' => $creatorUser->getId()->value(),
            'email' => $creatorUser->getEmail()->value(),
        ], $userData['creator']);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUserNotFound(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $nonExistentUserId = UuidMother::create()->value();

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUserEndpoint($nonExistentUserId),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUserWithInvalidUuid(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUserEndpoint('invalid-uuid'),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @param array<UserRole>           $requestingUserRoles
     * @param array<string, mixed>|null $targetUserSetup
     *
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    #[DataProvider('authorizationScenariosProvider')]
    public function testUserAuthorizationScenarios(
        array $requestingUserRoles,
        string $requestingUserEmail,
        ?array $targetUserSetup,
        int $expectedStatusCode,
        string $scenario,
    ): void {
        // Arrange
        $requestingUser = $this->createAndGetUser(
            roles: $requestingUserRoles,
            email: $requestingUserEmail,
        );

        $targetUser = $this->setupTargetUser($targetUserSetup, $requestingUser);
        $token = $this->loginAndGetTokenForUser($requestingUser);

        // Act
        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUserEndpoint($targetUser->getId()->value()),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        // Assert
        $this->assertEquals($expectedStatusCode, $response->getStatusCode(), "Failed scenario: {$scenario}");

        if (Response::HTTP_OK === $expectedStatusCode) {
            $userData = $this->extractResponseData($response);
            $this->assertEquals($targetUser->getId()->value(), $userData['id']);
            $this->assertEquals($targetUser->getEmail()->value(), $userData['email']);
        }
    }

    public static function authorizationScenariosProvider(): \Generator
    {
        yield 'Manager can view user they created' => [
            'requestingUserRoles' => [UserRole::Manager, UserRole::User],
            'requestingUserEmail' => 'manager@example.com',
            'targetUserSetup' => ['createdByRequesting' => true, 'email' => 'created.user@example.com'],
            'expectedStatusCode' => Response::HTTP_OK,
            'scenario' => 'Manager viewing user they created',
        ];

        yield 'Manager cannot view user they did not create' => [
            'requestingUserRoles' => [UserRole::Manager, UserRole::User],
            'requestingUserEmail' => 'manager@example.com',
            'targetUserSetup' => ['createdByOther' => true, 'email' => 'other.user@example.com'],
            'expectedStatusCode' => Response::HTTP_FORBIDDEN,
            'scenario' => 'Manager viewing user created by another manager',
        ];

        yield 'Manager can view themselves' => [
            'requestingUserRoles' => [UserRole::Manager, UserRole::User],
            'requestingUserEmail' => 'manager@example.com',
            'targetUserSetup' => ['self' => true, 'createdByRequesting' => true, 'email' => 'other.user@example.com'],
            'expectedStatusCode' => Response::HTTP_OK,
            'scenario' => 'Manager viewing themselves',
        ];

        yield 'User cannot view themselves in admin endpoint' => [
            'requestingUserRoles' => [UserRole::User],
            'requestingUserEmail' => 'user@example.com',
            'targetUserSetup' => ['self' => true],
            'expectedStatusCode' => Response::HTTP_FORBIDDEN,
            'scenario' => 'Regular user viewing themselves in admin endpoint',
        ];

        yield 'User cannot view other users' => [
            'requestingUserRoles' => [UserRole::User],
            'requestingUserEmail' => 'user@example.com',
            'targetUserSetup' => ['other' => true, 'email' => 'other.user@example.com'],
            'expectedStatusCode' => Response::HTTP_FORBIDDEN,
            'scenario' => 'Regular user viewing another user',
        ];
    }

    /**
     * @param array<string, mixed>|null $setup
     *
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    private function setupTargetUser(?array $setup, User $requestingUser): User
    {
        if (null === $setup) {
            return $this->createAndGetUser(roles: [UserRole::User], email: 'target@example.com');
        }

        if (isset($setup['self']) && true === $setup['self']) {
            return $requestingUser;
        }

        if (isset($setup['createdByRequesting']) && true === $setup['createdByRequesting']) {
            return $this->createAndGetUser(
                roles: [UserRole::User],
                email: $setup['email'],
                createdBy: $requestingUser->getId(),
            );
        }

        if (isset($setup['createdByOther']) && true === $setup['createdByOther']) {
            $otherManager = $this->createAndGetUser(
                roles: [UserRole::Manager, UserRole::User],
                email: 'other.manager@example.com',
            );

            return $this->createAndGetUser(
                roles: [UserRole::User],
                email: $setup['email'],
                createdBy: $otherManager->getId(),
            );
        }

        return $this->createAndGetUser(
            roles: [UserRole::User],
            email: $setup['email'] ?? 'default@example.com',
        );
    }
}
