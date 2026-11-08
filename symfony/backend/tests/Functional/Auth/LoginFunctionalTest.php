<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\User;
use App\Domain\User\UserRole;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use Symfony\Component\HttpFoundation\Response;

class LoginFunctionalTest extends FunctionalTestCase
{
    public function testLoginWithValidCredentials(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
                'password' => self::DEFAULT_USER_PASSWORD,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('token', $responseData['data']);
        $this->assertArrayHasKey('refresh_token', $responseData['data']);
        $this->assertNotEmpty($responseData['data']['token']);
        $this->assertNotEmpty($responseData['data']['refresh_token']);

        $payload = $this->decodeJwtPayload($responseData['data']['token']);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('email', $payload);
        $this->assertArrayHasKey('roles', $payload);
        $this->assertSame(self::DEFAULT_USER_ID, $payload['id']);
        $this->assertSame(self::DEFAULT_USER_EMAIL, $payload['email']);
        $this->assertSame(['ROLE_USER'], $payload['roles']);
    }

    public function testLoginWithInvalidPassword(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
                'password' => 'wrong_password',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testLoginWithNonExistentUser(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => 'nonexistent@example.com',
                'password' => 'password',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testLoginWithInvalidEmail(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => 'invalid-email',
                'password' => 'password',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testLoginWithMissingEmail(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'password' => 'password',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testLoginWithMissingPassword(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => 'nonexistent@example.com',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @throws InvalidUuidException
     * @throws InfrastructureException
     * @throws SecurityException
     */
    public function testLoginWithAdminUser(): void
    {
        // Crear un usuario administrador
        $adminUser = new User(
            id: UuidMother::create(),
            email: new Email('admin@example.com'),
            roles: [UserRole::Admin, UserRole::User],
            passwordHash: $this->hashPassword('admin@example.com', [UserRole::Admin, UserRole::User], 'admin_password'),
            createdAt: new \DateTimeImmutable(),
        );

        $this->getUserRepository()->put($adminUser);

        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => 'admin@example.com',
                'password' => 'admin_password',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('token', $responseData['data']);
        $this->assertNotEmpty($responseData['data']['token']);

        $payload = $this->decodeJwtPayload($responseData['data']['token']);
        $this->assertContains('ROLE_ADMIN', $payload['roles']);
        $this->assertContains('ROLE_USER', $payload['roles']);
    }

    public function testLoginReturnsValidJwtToken(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
                'password' => self::DEFAULT_USER_PASSWORD,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $token = $responseData['data']['token'];

        // Verificar que el token tiene el formato JWT (3 partes separadas por puntos)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'El token JWT debe tener 3 partes');

        // Verificar que cada parte está en base64
        foreach ($parts as $index => $part) {
            if ($index < 2) { // Header y payload
                $decoded = base64_decode(strtr($part, '-_', '+/'), true);
                $this->assertNotFalse($decoded, "La parte $index del token debe ser base64 válido");
            }
        }
    }

    public function testLoginCreatesRefreshToken(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
                'password' => self::DEFAULT_USER_PASSWORD,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $refreshToken = $responseData['data']['refresh_token'];

        // Verificar que el refresh token no está vacío y tiene longitud adecuada
        $this->assertNotEmpty($refreshToken);
        $this->assertGreaterThan(64, \strlen($refreshToken), 'El refresh token debe tener al menos 64 caracteres');
    }
}
