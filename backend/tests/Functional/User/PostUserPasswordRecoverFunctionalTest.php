<?php

declare(strict_types=1);

namespace App\Tests\Functional\User;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\UserCriteria;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\HelperTrait\Endpoints\AuthEndpoints;
use Symfony\Component\HttpFoundation\Response;

class PostUserPasswordRecoverFunctionalTest extends FunctionalTestCase
{
    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     */
    public function testPasswordRecoveryWithValidEmail(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::passwordRecoverEndpoint(),
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Password recovery email sent successfully', $responseData['message']);

        // Verify that the user has a password reset token
        $userRepository = $this->getUserRepository();
        $user = $userRepository->findOneBy(
            UserCriteria::createEmpty()->filterByEmail(new Email(self::DEFAULT_USER_EMAIL))
        );

        $this->assertNotNull($user->getPasswordResetToken());
        $this->assertNotNull($user->getPasswordResetTokenExpiration());
        $this->assertEquals(64, \strlen($user->getPasswordResetToken()));
        $this->assertGreaterThan(new \DateTimeImmutable(), $user->getPasswordResetTokenExpiration());
    }

    public function testPasswordRecoveryWithNonExistentEmail(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::passwordRecoverEndpoint(),
            body: [
                'email' => 'nonexistent@example.com',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        // For security reasons, we return success even if the email doesn't exist
        // to prevent user enumeration attacks
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $responseData = json_decode($content, true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Password recovery email sent successfully', $responseData['message']);
    }

    public function testPasswordRecoveryWithInvalidEmail(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::passwordRecoverEndpoint(),
            body: [
                'email' => 'invalid-email',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testPasswordRecoveryWithMissingEmail(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::passwordRecoverEndpoint(),
            body: [],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testPasswordRecoveryWithEmptyEmail(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::passwordRecoverEndpoint(),
            body: [
                'email' => '',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * El patrón HTML5 por defecto de Assert\Email acepta este email, pero FILTER_VALIDATE_EMAIL lo
     * rechaza en el value object. Antes de mapear las excepciones de value object a 422 esto era un
     * 500; ahora es un error del cliente, y el contrato OpenAPI lo declara.
     */
    public function testPasswordRecoveryWithEmailRejectedByTheValueObject(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::passwordRecoverEndpoint(),
            body: [
                'email' => '.user@example.com',
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    /**
     * @throws \DateMalformedStringException
     * @throws InfrastructureException
     * @throws UserNotFoundException
     */
    public function testPasswordRecoveryTokenExpirationIsSet(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::passwordRecoverEndpoint(),
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Verify token expiration is approximately 24 hours from now
        $userRepository = $this->getUserRepository();
        $user = $userRepository->findOneBy(
            UserCriteria::createEmpty()->filterByEmail(new Email(self::DEFAULT_USER_EMAIL))
        );

        $expiration = $user->getPasswordResetTokenExpiration();
        $this->assertNotNull($expiration);

        $now = new \DateTimeImmutable();
        $expectedExpiration = $now->modify('+24 hours');
        $diff = $expiration->getTimestamp() - $expectedExpiration->getTimestamp();

        // Allow 5 seconds tolerance
        $this->assertLessThan(5, abs($diff));
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     */
    public function testPasswordRecoveryUpdatesExistingToken(): void
    {
        // First request
        $firstResponse = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::passwordRecoverEndpoint(),
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_OK, $firstResponse->getStatusCode());

        $userRepository = $this->getUserRepository();
        $user = $userRepository->findOneBy(
            UserCriteria::createEmpty()->filterByEmail(new Email(self::DEFAULT_USER_EMAIL))
        );
        $firstToken = $user->getPasswordResetToken();

        // Second request
        $secondResponse = $this->makeRequest(
            method: 'POST',
            uri: AuthEndpoints::passwordRecoverEndpoint(),
            body: [
                'email' => self::DEFAULT_USER_EMAIL,
            ],
            headers: ['CONTENT_TYPE' => 'application/json'],
        );

        $this->assertEquals(Response::HTTP_OK, $secondResponse->getStatusCode());

        $user = $userRepository->findOneBy(
            UserCriteria::createEmpty()->filterByEmail(new Email(self::DEFAULT_USER_EMAIL))
        );
        $secondToken = $user->getPasswordResetToken();

        $this->assertNotEquals($firstToken, $secondToken);
    }
}
