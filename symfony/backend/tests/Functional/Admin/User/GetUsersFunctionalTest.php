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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class GetUsersFunctionalTest extends FunctionalTestCase
{
    public function testGetUsersWithoutAuthentication(): void
    {
        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     * @throws InfrastructureException
     */
    public function testGetUsersWithNonAdminUser(): void
    {
        $user = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'regular.user@example.com',
        );

        $token = $this->loginAndGetTokenForUser($user);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
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
    public function testThrowsForbiddenExceptionWhenManagerTriesToFilterByOtherUsersCreatedBy(): void
    {
        $manager1 = $this->createAndGetUser(
            roles: [UserRole::Manager, UserRole::User],
            email: 'manager1@example.com',
        );

        $manager2 = $this->createAndGetUser(
            roles: [UserRole::Manager, UserRole::User],
            email: 'manager2@example.com',
        );

        $token = $this->loginAndGetTokenForUser($manager1);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            queryParams: ['created_by' => $manager2->getId()->value()],
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
    public function testGetUsersWithAdminUser(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->extractResponseData($response);
        $responseMetadata = $this->extractResponseMetadata($response);

        $this->assertGreaterThanOrEqual(1, \count($responseData));
        $this->assertArrayHasKey('page', $responseMetadata);
        $this->assertArrayHasKey('total_pages', $responseMetadata);
        $this->assertArrayHasKey('limit', $responseMetadata);
        $this->assertArrayHasKey('total', $responseMetadata);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUsersReturnsCorrectStructure(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = $this->extractResponseData($response);
        $responseMetadata = $this->extractResponseMetadata($response);

        $this->assertArrayHasKey('page', $responseMetadata);
        $this->assertArrayHasKey('total_pages', $responseMetadata);
        $this->assertArrayHasKey('limit', $responseMetadata);
        $this->assertArrayHasKey('total', $responseMetadata);

        $this->assertGreaterThanOrEqual(1, \count($responseData));

        if (\count($responseData) > 0) {
            $user = $responseData[0];
            $this->assertArrayHasKey('id', $user);
            $this->assertArrayHasKey('email', $user);
            $this->assertArrayHasKey('roles', $user);
            $this->assertArrayHasKey('created_at', $user);
            $this->assertIsArray($user['roles']);
        }
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUsersReturnsCreatorWhenHydratedByAdminController(): void
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
            uri: UserEndpoints::getUsersEndpoint(),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->extractResponseData($response);

        $userData = array_values(array_filter(
            $responseData,
            fn (array $user): bool => $user['id'] === $targetUser->getId()->value(),
        ));

        $this->assertCount(1, $userData);
        $this->assertSame($creatorUser->getId()->value(), $userData[0]['created_by']);
        $this->assertSame([
            'id' => $creatorUser->getId()->value(),
            'email' => $creatorUser->getEmail()->value(),
        ], $userData[0]['creator']);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUsersWithPagination(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        // Crear usuarios adicionales
        $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'user1@example.com',
        );
        $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'user2@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        // Primera página
        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            queryParams: ['page' => 1],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseMetadata = $this->extractResponseMetadata($response);

        $this->assertEquals(1, $responseMetadata['page']);
        $this->assertEquals(10, $responseMetadata['limit']);
        $this->assertGreaterThanOrEqual(3, $responseMetadata['total']);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUsersWithSearchFilter(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'john.doe@example.com',
        );
        $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'jane.smith@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            queryParams: ['search' => 'john'],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->extractResponseData($response);

        $this->assertGreaterThanOrEqual(1, \count($responseData));

        // Verificar que el resultado contiene el usuario buscado
        $emails = array_column($responseData, 'email');
        $this->assertContains('john.doe@example.com', $emails);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUsersWithRoleFilter(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'regular.user@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            queryParams: ['role' => 'ROLE_ADMIN'],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->extractResponseData($response);

        $this->assertGreaterThanOrEqual(1, \count($responseData));

        // Verificar que todos los usuarios tienen el rol ROLE_ADMIN
        foreach ($responseData as $user) {
            $this->assertContains('ROLE_ADMIN', $user['roles']);
        }
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUsersWithSorting(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'alpha@example.com',
        );
        $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'zeta@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        // Ordenar ascendente
        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            queryParams: [
                'sort_by' => 'email',
                'sort_dir' => 'asc',
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->extractResponseData($response);
        $this->assertGreaterThanOrEqual(2, \count($responseData));

        // Verificar que está ordenado
        $emails = array_column($responseData, 'email');
        $sortedEmails = $emails;
        sort($sortedEmails);
        $this->assertEquals($sortedEmails, $emails);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetUsersWithInvalidPage(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            queryParams: ['page' => 0],
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
    public function testGetUsersWithInvalidSortDirection(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            queryParams: [
                'sort_by' => 'email',
                'sort_dir' => 'invalid',
            ],
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed>             $loggedUser
     * @param array<int, array<string, mixed>> $users
     * @param array<int, string>               $expectedContainsEmails
     * @param array<int, string>               $expectedNotContainsEmails
     *
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    #[DataProvider('autoScopeAndFilteringProvider')]
    public function testAutoScopeAndFilteringByCreatedBy(
        array $loggedUser,
        array $users,
        ?string $filterCreatedByEmail,
        array $expectedContainsEmails,
        array $expectedNotContainsEmails,
    ): void {
        $createdUsers[] = $loggedUser = $this->createAndGetUser(
            roles: $loggedUser['roles'],
            email: $loggedUser['email'],
        );

        foreach ($users as $user) {
            $createdBy = $user['createdBy'];

            if (null !== $createdBy) {
                $createdUsers[] = $createdBy = ($loggedUser->getEmail()->value() === $createdBy) ?
                    $loggedUser :
                    $this->createAndGetUser(
                        roles: [UserRole::Manager, UserRole::User],
                        email: $createdBy,
                    );
            }

            $createdUsers[] = $this->createAndGetUser(
                roles: [UserRole::User],
                email: $user['email'],
                createdBy: $createdBy?->getId(),
            );
        }

        $token = $this->loginAndGetTokenForUser($loggedUser);

        $queryParams = [];
        if (null !== $filterCreatedByEmail) {
            $queryParams['created_by'] = array_reduce(
                $createdUsers,
                fn (?string $carry, User $user) => $user->getEmail()->value() === $filterCreatedByEmail ? $user->getId()->value() : $carry,
                null,
            );
        }

        $response = $this->makeRequest(
            method: 'GET',
            uri: UserEndpoints::getUsersEndpoint(),
            queryParams: $queryParams,
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $responseData = $this->extractResponseData($response);
        $emails = array_column($responseData, 'email');

        foreach ($expectedContainsEmails as $email) {
            $this->assertContains($email, $emails);
        }

        foreach ($expectedNotContainsEmails as $email) {
            $this->assertNotContains($email, $emails);
        }
    }

    public static function autoScopeAndFilteringProvider(): \Generator
    {
        yield 'manager with auto-scope sees only users they created' => [
            'loggedUser' => [
                'email' => 'manager1@example.com',
                'roles' => [UserRole::Manager],
            ],
            'users' => [
                [
                    'email' => 'user.by.m1@example.com',
                    'createdBy' => 'manager1@example.com',
                ],
                [
                    'email' => 'user.by.m2@example.com',
                    'createdBy' => null,
                ],
            ],
            'filterCreatedByEmail' => null,
            'expectedContainsEmails' => ['user.by.m1@example.com'],
            'expectedNotContainsEmails' => ['user.by.m2@example.com'],
        ];

        yield 'admin can filter by created_by without restrictions' => [
            'loggedUser' => [
                'email' => 'admin@example.com',
                'roles' => [UserRole::Admin],
            ],
            'users' => [
                [
                    'email' => 'user.by.m1@example.com',
                    'createdBy' => 'manager1@example.com',
                ],
                [
                    'email' => 'user.by.m2@example.com',
                    'createdBy' => null,
                ],
            ],
            'filterCreatedByEmail' => 'manager1@example.com',
            'expectedContainsEmails' => ['user.by.m1@example.com'],
            'expectedNotContainsEmails' => [],
        ];

        yield 'admin sees all users without auto-scope' => [
            'loggedUser' => [
                'email' => 'admin@example.com',
                'roles' => [UserRole::Admin],
            ],
            'users' => [
                [
                    'email' => 'user.by.m1@example.com',
                    'createdBy' => 'manager1@example.com',
                ],
                [
                    'email' => 'user.by.m2@example.com',
                    'createdBy' => null,
                ],
            ],
            'filterCreatedByEmail' => null,
            'expectedContainsEmails' => ['user.by.m1@example.com', 'user.by.m2@example.com'],
            'expectedNotContainsEmails' => [],
        ];
    }
}
