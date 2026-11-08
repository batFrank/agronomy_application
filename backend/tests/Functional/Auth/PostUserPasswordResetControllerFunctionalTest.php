<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Mother\Domain\User\UserMother;
use Symfony\Component\HttpFoundation\Response;

class PostUserPasswordResetControllerFunctionalTest extends FunctionalTestCase
{
    private const string ENDPOINT = '/auth/password-reset';

    public function testSuccessfulPasswordReset(): void
    {
        $token = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4';
        $expiration = new \DateTimeImmutable('+1 hour');
        $oldPasswordHash = 'old_password_hash';
        $newPassword = 'newPassword123';

        $user = UserMother::create(
            passwordHash: $oldPasswordHash,
            passwordResetToken: $token,
            passwordResetTokenExpiration: $expiration
        );

        /** @var UserRepository $userRepository */
        $userRepository = $this->client->getContainer()->get('App\Domain\User\UserRepository');
        $userRepository->put($user);

        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: [
                'reset_token' => $token,
                'new_password' => $newPassword,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertJson($content);
        $responseDecoded = json_decode($content, true);

        $this->assertArrayHasKey('message', $responseDecoded);
        $this->assertEquals('Password reset successfully', $responseDecoded['message']);

        // Verificar que la contraseña fue actualizada
        $updatedUser = $userRepository->findOneBy(
            UserCriteria::createEmpty()->filterById($user->getId())
        );
        $this->assertNotEquals($oldPasswordHash, $updatedUser->getPasswordHash());
        $this->assertTrue(password_verify($newPassword, $updatedUser->getPasswordHash()));

        // Verificar que el token fue limpiado
        $this->assertNull($updatedUser->getPasswordResetToken());
        $this->assertNull($updatedUser->getPasswordResetTokenExpiration());
    }

    public function testPasswordResetWithInvalidToken(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: [
                'reset_token' => 'z9y8x7w6v5u4t3s2r1q0p9o8n7m6l5k4j3i2h1g0f9e8d7c6b5a4z9y8x7w6',
                'new_password' => 'newPassword123',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertJson($content);
        $responseDecoded = json_decode($content, true);

        $this->assertArrayHasKey('message', $responseDecoded);
        $this->assertEquals('Invalid or expired reset token', $responseDecoded['message']);
    }

    public function testPasswordResetWithExpiredToken(): void
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
            body: [
                'reset_token' => $token,
                'new_password' => 'newPassword123',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertJson($content);
        $responseDecoded = json_decode($content, true);

        $this->assertArrayHasKey('message', $responseDecoded);
        $this->assertEquals('Invalid or expired reset token', $responseDecoded['message']);
    }

    public function testPasswordResetWithMissingToken(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: [
                'new_password' => 'newPassword123',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = $this->extractResponseMetadata($response);
        $this->assertArrayHasKey('violations', $responseData);
    }

    public function testPasswordResetWithMissingPassword(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: [
                'reset_token' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = $this->extractResponseMetadata($response);
        $this->assertArrayHasKey('violations', $responseData);
    }

    public function testPasswordResetWithTooShortPassword(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: [
                'reset_token' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4',
                'new_password' => '123',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = $this->extractResponseMetadata($response);
        $this->assertArrayHasKey('violations', $responseData);
    }

    public function testPasswordResetWithTooShortToken(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: [
                'reset_token' => 'short',
                'new_password' => 'newPassword123',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = $this->extractResponseMetadata($response);
        $this->assertArrayHasKey('violations', $responseData);
    }

    public function testPasswordResetWithInvalidJson(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: self::ENDPOINT,
            body: 'invalid-json'
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
