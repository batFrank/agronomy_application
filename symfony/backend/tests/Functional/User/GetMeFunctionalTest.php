<?php

declare(strict_types=1);

namespace App\Tests\Functional\User;

use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\UserRole;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\HelperTrait\Endpoints\MeEndpoints;
use Symfony\Component\HttpFoundation\Response;

class GetMeFunctionalTest extends FunctionalTestCase
{
    public function testGetMeWithoutAuthentication(): void
    {
        $response = $this->makeRequest(
            method: 'GET',
            uri: MeEndpoints::getMeEndpoint(),
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetMeReturnsLoggedUserData(): void
    {
        $user = $this->createAndGetUser(
            roles: [UserRole::User],
            email: 'profile.user@example.com',
        );

        $token = $this->loginAndGetTokenForUser($user);

        $response = $this->makeRequest(
            method: 'GET',
            uri: MeEndpoints::getMeEndpoint(),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->extractResponseData($response);
        $this->assertEquals($user->getId()->value(), $data['id']);
        $this->assertEquals($user->getEmail()->value(), $data['email']);
        $this->assertContains('ROLE_USER', $data['roles']);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetMeReturnsCorrectStructure(): void
    {
        $token = $this->loginAndGetToken();

        $response = $this->makeRequest(
            method: 'GET',
            uri: MeEndpoints::getMeEndpoint(),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->extractResponseData($response);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('roles', $data);
        $this->assertArrayHasKey('created_at', $data);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testGetMeReturnsOwnDataRegardlessOfRole(): void
    {
        $adminUser = $this->createAndGetUser(
            roles: [UserRole::Admin, UserRole::User],
            email: 'admin.me@example.com',
        );

        $token = $this->loginAndGetTokenForUser($adminUser);

        $response = $this->makeRequest(
            method: 'GET',
            uri: MeEndpoints::getMeEndpoint(),
            headers: ['HTTP_Authorization' => 'Bearer ' . $token],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->extractResponseData($response);
        $this->assertEquals($adminUser->getId()->value(), $data['id']);
        $this->assertEquals($adminUser->getEmail()->value(), $data['email']);
    }
}
