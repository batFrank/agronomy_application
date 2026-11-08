<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth\OTP;

use App\Domain\OTP\OtpCriteria;
use App\Domain\OTP\OtpRepository;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Resource\ResourceType;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\HelperTrait\ApiKeyHelperTrait;
use App\Tests\Functional\HelperTrait\OtpHelperTrait;
use Symfony\Component\HttpFoundation\Response;

class GenerateLogin2FAOtpFunctionalTest extends FunctionalTestCase
{
    use ApiKeyHelperTrait;
    use OtpHelperTrait;

    public function testGenerateLogin2FAOtpUnauthorized(): void
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
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testGenerateLogin2FAOtp(): void
    {
        $user = $this->createAndGetUser(
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
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $email = self::getMailerMessage();
        $this->assertNotNull($email);
        $this->assertEmailHeaderSame($email, 'To', 'user-test@example.com');
        $this->assertEmailHtmlBodyContains($email, 'Your one-time password is');

        /** @var OtpRepository $otpRepository */
        $otpRepository = $this->getOtpRepository();
        $otp = $otpRepository->findOneBy(
            OtpCriteria::createEmpty()->filterByResource(new Resource(
                id: $user->getId(),
                type: ResourceType::User
            ))
        );
        $this->assertEquals(Purpose::Login2FA, $otp->getPurpose());
        $this->assertEquals(0, $otp->getAttempts());
        $this->assertEquals(1, $otp->getMaxAttempts());
        $this->assertFalse($otp->isRevoked());
        $this->assertNull($otp->getConsumedAt());
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
            ],
            headers: [
                'HTTP_X_API_KEY' => 'test_api_key',
            ]
        );

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    private function getEndpoint(): string
    {
        return '/auth/otp/login-2fa/generate';
    }
}
