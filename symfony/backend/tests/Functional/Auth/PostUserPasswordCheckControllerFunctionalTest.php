<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Domain\User\UserRepository;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Mother\Domain\User\UserMother;
use Symfony\Component\HttpFoundation\Response;

class PostUserPasswordCheckControllerFunctionalTest extends FunctionalTestCase
{
    private const string ENDPOINT = '/auth/password-check';

    public function testValidTokenReturnsTrue(): void
    {
        $token = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4';
        $expiration = new \DateTimeImmutable('+1 hour');
        $user = UserMother::create(
            passwordResetToken: $token,
            passwordResetTokenExpiration: $expiration
        );

        /** @var UserRepository $userRepository */
        $userRepository = $this->client->getContainer()->get('App\Domain\User\UserRepository');
        $userRepository->put($user);

        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: ['reset_token' => $token]
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->extractResponseData($response);
        $this->assertArrayHasKey('valid', $responseData);
        $this->assertTrue($responseData['valid']);
    }

    public function testInvalidTokenReturnsFalse(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: ['reset_token' => 'z9y8x7w6v5u4t3s2r1q0p9o8n7m6l5k4j3i2h1g0f9e8d7c6b5a4z9y8x7w6']
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->extractResponseData($response);
        $this->assertArrayHasKey('valid', $responseData);
        $this->assertFalse($responseData['valid']);
    }

    public function testExpiredTokenReturnsFalse(): void
    {
        $token = 'b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4e5f6';
        $expiration = new \DateTimeImmutable('-1 hour'); // Expirado
        $user = UserMother::create(
            passwordResetToken: $token,
            passwordResetTokenExpiration: $expiration
        );

        /** @var UserRepository $userRepository */
        $userRepository = $this->client->getContainer()->get('App\Domain\User\UserRepository');
        $userRepository->put($user);

        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: ['reset_token' => $token]
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->extractResponseData($response);
        $this->assertArrayHasKey('valid', $responseData);
        $this->assertFalse($responseData['valid']);
    }

    public function testMissingTokenReturnsValidationError(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: []
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = $this->extractResponseMetadata($response);
        $this->assertArrayHasKey('violations', $responseData);
    }

    public function testEmptyTokenReturnsValidationError(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: ['reset_token' => '']
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = $this->extractResponseMetadata($response);
        $this->assertArrayHasKey('violations', $responseData);
    }

    public function testTooShortTokenReturnsValidationError(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: ['reset_token' => 'short']
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = $this->extractResponseMetadata($response);
        $this->assertArrayHasKey('violations', $responseData);
    }

    public function testInvalidJsonReturnsValidationError(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: 'invalid-json'
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
