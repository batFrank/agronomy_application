<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\HelperTrait\Endpoints\AuthEndpoints;
use Symfony\Component\HttpFoundation\Response;

class RefreshTokenFunctionalTest extends FunctionalTestCase
{
    public function testRefreshTokenWithValidToken(): void
    {
        // Primero hacer login para obtener un refresh token
        $loginResponse = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
                'password' => self::DEFAULT_USER_PASSWORD,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_OK, $loginResponse->getStatusCode());

        $content = $loginResponse->getContent();
        $this->assertNotFalse($content);
        $loginData = json_decode($content, true);
        $this->assertIsArray($loginData);
        $this->assertIsArray($loginData['data']);
        $refreshToken = $loginData['data']['refresh_token'];

        // Ahora usar el refresh token para obtener nuevos tokens
        $refreshResponse = $this->makeRequest('POST', AuthEndpoints::refreshTokenEndpoint(), [
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals(Response::HTTP_OK, $refreshResponse->getStatusCode());

        $content = $refreshResponse->getContent();
        $this->assertNotFalse($content);
        $refreshData = json_decode($content, true);
        $this->assertIsArray($refreshData);
        $this->assertArrayHasKey('data', $refreshData);
        $this->assertIsArray($refreshData['data']);
        $this->assertArrayHasKey('token', $refreshData['data']);
        $this->assertArrayHasKey('refresh_token', $refreshData['data']);
        $this->assertNotEmpty($refreshData['data']['token']);
        $this->assertNotEmpty($refreshData['data']['refresh_token']);

        $payload = $this->decodeJwtPayload($refreshData['data']['token']);

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('email', $payload);
        $this->assertArrayHasKey('roles', $payload);
        $this->assertSame(self::DEFAULT_USER_ID, $payload['id']);
        $this->assertSame(self::DEFAULT_USER_EMAIL, $payload['email']);
        $this->assertSame(['ROLE_USER'], $payload['roles']);

        // Verificar que el nuevo refresh token es diferente del anterior
        $this->assertNotEquals($refreshToken, $refreshData['data']['refresh_token']);
    }

    public function testRefreshTokenCanOnlyBeUsedOnce(): void
    {
        // Hacer login
        $loginResponse = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
                'password' => self::DEFAULT_USER_PASSWORD,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $content = $loginResponse->getContent();
        $this->assertNotFalse($content);
        $loginData = json_decode($content, true);
        $this->assertIsArray($loginData);
        $this->assertIsArray($loginData['data']);
        $refreshToken = $loginData['data']['refresh_token'];

        // Usar el refresh token por primera vez
        $firstRefreshResponse = $this->makeRequest('POST', AuthEndpoints::refreshTokenEndpoint(), [
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals(Response::HTTP_OK, $firstRefreshResponse->getStatusCode());

        // Intentar usar el mismo refresh token de nuevo (debe fallar)
        $secondRefreshResponse = $this->makeRequest('POST', AuthEndpoints::refreshTokenEndpoint(), [
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $secondRefreshResponse->getStatusCode());
    }

    public function testRefreshTokenWithInvalidToken(): void
    {
        $response = $this->makeRequest('POST', AuthEndpoints::refreshTokenEndpoint(), [
            'refresh_token' => 'invalid_token_12345',
        ]);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testRefreshTokenWithMissingToken(): void
    {
        $response = $this->makeRequest('POST', AuthEndpoints::refreshTokenEndpoint(), []);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testRefreshTokenGeneratesValidJwtToken(): void
    {
        // Hacer login
        $loginResponse = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
                'password' => self::DEFAULT_USER_PASSWORD,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $content = $loginResponse->getContent();
        $this->assertNotFalse($content);
        $loginData = json_decode($content, true);
        $this->assertIsArray($loginData);
        $this->assertIsArray($loginData['data']);
        $refreshToken = $loginData['data']['refresh_token'];

        // Usar el refresh token
        $refreshResponse = $this->makeRequest('POST', AuthEndpoints::refreshTokenEndpoint(), [
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals(Response::HTTP_OK, $refreshResponse->getStatusCode());

        $content = $refreshResponse->getContent();
        $this->assertNotFalse($content);
        $refreshData = json_decode($content, true);
        $this->assertIsArray($refreshData);
        $this->assertIsArray($refreshData['data']);
        $this->assertIsString($refreshData['data']['token']);
        $newToken = $refreshData['data']['token'];

        // Verificar que el nuevo token tiene el formato JWT
        $parts = explode('.', $newToken);
        $this->assertCount(3, $parts, 'El token JWT debe tener 3 partes');

        // Verificar que cada parte está en base64
        foreach ($parts as $index => $part) {
            if ($index < 2) { // Header y payload
                $decoded = base64_decode(strtr($part, '-_', '+/'), true);
                $this->assertNotFalse($decoded, "La parte $index del token debe ser base64 válido");
            }
        }
    }

    public function testRefreshTokenChain(): void
    {
        // Hacer login
        $loginResponse = $this->makeRequest(
            method: 'POST',
            uri: '/auth/login',
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
                'password' => self::DEFAULT_USER_PASSWORD,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $content = $loginResponse->getContent();
        $this->assertNotFalse($content);
        $loginData = json_decode($content, true);
        $this->assertIsArray($loginData);
        $this->assertIsArray($loginData['data']);
        $refreshToken1 = $loginData['data']['refresh_token'];

        // Primera renovación
        $refresh1Response = $this->makeRequest('POST', AuthEndpoints::refreshTokenEndpoint(), [
            'refresh_token' => $refreshToken1,
        ]);

        $this->assertEquals(Response::HTTP_OK, $refresh1Response->getStatusCode());
        $content = $refresh1Response->getContent();
        $this->assertNotFalse($content);
        $refresh1Data = json_decode($content, true);
        $this->assertIsArray($refresh1Data);
        $this->assertIsArray($refresh1Data['data']);
        $refreshToken2 = $refresh1Data['data']['refresh_token'];

        // Segunda renovación
        $refresh2Response = $this->makeRequest('POST', AuthEndpoints::refreshTokenEndpoint(), [
            'refresh_token' => $refreshToken2,
        ]);

        $this->assertEquals(Response::HTTP_OK, $refresh2Response->getStatusCode());
        $content = $refresh2Response->getContent();
        $this->assertNotFalse($content);
        $refresh2Data = json_decode($content, true);
        $this->assertIsArray($refresh2Data);
        $this->assertIsArray($refresh2Data['data']);
        $refreshToken3 = $refresh2Data['data']['refresh_token'];

        // Verificar que todos los tokens son diferentes
        $this->assertNotEquals($refreshToken1, $refreshToken2);
        $this->assertNotEquals($refreshToken2, $refreshToken3);
        $this->assertNotEquals($refreshToken1, $refreshToken3);
    }
}
