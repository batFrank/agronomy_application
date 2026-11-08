<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth\OTP;

use App\Domain\OTP\Exception\OtpNotFoundException;
use App\Domain\OTP\OtpCriteria;
use App\Domain\OTP\OtpRepository;
use App\Domain\OTP\Purpose;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Resource\ResourceType;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\HelperTrait\ApiKeyHelperTrait;
use App\Tests\Functional\HelperTrait\OtpHelperTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;

#[CoversNothing]
class ValidateLogin2FAOtpFunctionalTest extends FunctionalTestCase
{
    use ApiKeyHelperTrait;
    use OtpHelperTrait;

    public function testValidateLogin2FAOtpUnauthorized(): void
    {
        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => 'test@example.com',
            ],
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testBadRequest(): void
    {
        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testWhenUserDoesNotExists(): void
    {
        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => 'user-test@example.com',
                'code' => '12345A',
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @throws SecurityException
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testValidateLogin2FAOtpWhenCodeNotFound(): void
    {
        $this->createAndGetUser(
            email: 'user-test@example.com'
        );

        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => 'user-test@example.com',
                'code' => '12345A',
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @throws SecurityException
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testValidateLogin2FAOtpWhenIsRevoked(): void
    {
        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        $user = $this->createAndGetUser(
            email: 'user-test@example.com'
        );

        $this->setAndGetOtpInRepository(
            resource: new Resource(
                id: $user->getId(),
                type: ResourceType::User,
            ),
            purpose: Purpose::Login2FA,
            codeSecret: hash_hmac(algo: 'sha256', data: '123456', key: 'test-secret-value'),
            attempts: 0,
            maxAttempts: 1,
            revoked: true,
            createdAt: new \DateTimeImmutable(),
        );

        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => 'user-test@example.com',
                'code' => '12345A',
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @throws SecurityException
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testValidateLogin2FAOtpWhenMaxAttemptsReached(): void
    {
        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        $user = $this->createAndGetUser(
            email: 'user-test@example.com'
        );

        $this->setAndGetOtpInRepository(
            resource: new Resource(
                id: $user->getId(),
                type: ResourceType::User,
            ),
            purpose: Purpose::Login2FA,
            codeSecret: hash_hmac(algo: 'sha256', data: '123456', key: 'test-secret-value'),
            attempts: 1,
            maxAttempts: 1,
            revoked: false,
            createdAt: new \DateTimeImmutable(),
        );

        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => 'user-test@example.com',
                'code' => '12345A',
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @throws SecurityException
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws OtpNotFoundException
     */
    public function testValidateLogin2FAOtpWhenCodeIsIncorrect(): void
    {
        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        $user = $this->createAndGetUser(
            email: 'user-test@example.com'
        );

        $otp = $this->setAndGetOtpInRepository(
            resource: new Resource(
                id: $user->getId(),
                type: ResourceType::User,
            ),
            purpose: Purpose::Login2FA,
            codeSecret: hash_hmac(algo: 'sha256', data: '123456', key: 'test-secret-value'),
            attempts: 0,
            maxAttempts: 1,
            revoked: false,
            createdAt: new \DateTimeImmutable(),
        );

        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => 'user-test@example.com',
                'code' => '12345A',
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        /** @var OtpRepository $repository */
        $repository = $this->getOtpRepository();
        $result = $repository->findOneBy(OtpCriteria::createById($otp->getId()));
        $this->assertNotNull($result->getConsumedAt());
        $this->assertEquals(1, $result->getAttempts());
    }

    /**
     * @throws \DateMalformedStringException
     * @throws SecurityException
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws OtpNotFoundException
     */
    public function testValidateLogin2FAOtpWhenCodeIsAlreadyExpired(): void
    {
        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        $user = $this->createAndGetUser(
            email: 'user-test@example.com'
        );

        $otp = $this->setAndGetOtpInRepository(
            resource: new Resource(
                id: $user->getId(),
                type: ResourceType::User,
            ),
            purpose: Purpose::Login2FA,
            codeSecret: hash_hmac(algo: 'sha256', data: '123456', key: 'test-secret-value'),
            attempts: 0,
            maxAttempts: 1,
            revoked: false,
            expiresAt: new \DateTimeImmutable()->modify('-2 minutes'),
            createdAt: new \DateTimeImmutable(),
        );

        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => 'user-test@example.com',
                'code' => '123456',
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        /** @var OtpRepository $repository */
        $repository = $this->getOtpRepository();
        $result = $repository->findOneBy(OtpCriteria::createById($otp->getId()));
        $this->assertNotNull($result->getConsumedAt());
        $this->assertEquals(1, $result->getAttempts());
    }

    /**
     * @throws \DateMalformedStringException
     * @throws SecurityException
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws OtpNotFoundException
     */
    public function testValidateLogin2FAOtpWhenCodeIsValid(): void
    {
        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        $user = $this->createAndGetUser(
            email: 'user-test@example.com'
        );

        $otp = $this->setAndGetOtpInRepository(
            resource: new Resource(
                id: $user->getId(),
                type: ResourceType::User,
            ),
            purpose: Purpose::Login2FA,
            codeSecret: hash_hmac(algo: 'sha256', data: '123456', key: 'test-secret-value'),
            attempts: 0,
            maxAttempts: 1,
            revoked: false,
            expiresAt: new \DateTimeImmutable()->modify('+1 hour'),
            createdAt: new \DateTimeImmutable(),
        );

        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => 'user-test@example.com',
                'code' => '123456',
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        /** @var OtpRepository $repository */
        $repository = $this->getOtpRepository();
        $result = $repository->findOneBy(OtpCriteria::createById($otp->getId()));
        $this->assertNotNull($result->getConsumedAt());
        $this->assertEquals(1, $result->getAttempts());
    }

    public function testIntegrationFlow(): void
    {
        $user = $this->createAndGetUser(
            email: 'user-test@example.com'
        );

        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        // Request an otp code
        $response = $this->makeRequest(
            method: 'POST',
            uri: '/auth/otp/login-2fa/generate',
            body: [
                'email' => 'user-test@example.com',
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        /** @var ?Email $email */
        $email = self::getMailerMessage();
        $this->assertNotNull($email);
        $this->assertEmailHeaderSame($email, 'To', 'user-test@example.com');
        $this->assertEmailHtmlBodyContains($email, 'Your one-time password is');

        $htmlBody = $email->getHtmlBody();

        $this->assertNotNull($htmlBody);

        $matches = [];
        if (1 !== preg_match('/<p>Your one-time password is (.*)<\/p>/', (string) $htmlBody, $matches)) {
            $this->fail('Could not find the otp code in the email');
        }

        $this->assertCount(2, $matches);
        $code = $matches[1];
        $this->assertNotEmpty($code);

        // 2. Validate the otp code
        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => 'user-test@example.com',
                'code' => $code,
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * El patrón HTML5 por defecto de Assert\Email acepta este email, pero FILTER_VALIDATE_EMAIL lo
     * rechaza en el value object. Antes de mapear las excepciones de value object a 422 esto era un
     * 500; ahora es un error del cliente, y el contrato OpenAPI lo declara.
     */
    public function testEmailRejectedByTheValueObject(): void
    {
        $this->setAndGetApiKeyInRepository(
            apiKey: 'test_api_key',
            active: true,
        );

        $response = $this->makeRequest(
            method: 'POST',
            uri: $this->getEndpoint(),
            body: [
                'email' => '.user@example.com',
                'code' => '123456',
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    private function getEndpoint(): string
    {
        return '/auth/otp/login-2fa/validate';
    }
}
