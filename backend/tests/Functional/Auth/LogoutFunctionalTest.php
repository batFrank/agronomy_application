<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\HelperTrait\Endpoints\AuthEndpoints;
use Symfony\Component\HttpFoundation\Response;

class LogoutFunctionalTest extends FunctionalTestCase
{
    public function testLogoutWithValidRefreshToken(): void
    {
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
        $refreshToken = $loginData['data']['refresh_token'];

        $logoutResponse = $this->makeRequest('POST', AuthEndpoints::logoutEndpoint(), [
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals(Response::HTTP_OK, $logoutResponse->getStatusCode());

        $content = $logoutResponse->getContent();
        $this->assertNotFalse($content);
        $logoutData = json_decode($content, true);
        $this->assertIsArray($logoutData);
        $this->assertArrayHasKey('message', $logoutData);
        $this->assertSame('Logout successful', $logoutData['message']);

        $response = $this->makeRequest('POST', '/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $message = $this->extractResponseMessage($response);
        $this->assertSame('Refresh token not found or already used', $message);
    }

    public function testLogoutWithoutRefreshToken(): void
    {
        $response = $this->makeRequest('POST', AuthEndpoints::logoutEndpoint(), []);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testLogoutWithInvalidRefreshToken(): void
    {
        $response = $this->makeRequest('POST', AuthEndpoints::logoutEndpoint(), [
            'refresh_token' => 'invalid-token',
        ]);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('error', $responseData);
    }
}
