<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Application\Security\User as SecurityUser;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Tests\Functional\HelperTrait\UserHelperTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FunctionalTestCase extends WebTestCase
{
    use UserHelperTrait;

    protected const string DEFAULT_USER_ID = '019cae9e-65a9-7b6d-94b0-f9987f4b75cd';
    protected const string DEFAULT_USER_EMAIL = 'user@example.com';
    protected const string DEFAULT_USER_PASSWORD = '12345678';
    protected const UserRole DEFAULT_USER_ROLE = UserRole::User;
    protected static bool $debugMode = false;

    protected KernelBrowser $client;

    protected function log(string $message): void
    {
        if (!self::$debugMode) {
            return;
        }

        fwrite(STDOUT, $message . PHP_EOL);
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = self::createClient();
            $this->restoreDefaultUserState();
        } catch (\Exception $e) {
            $this->fail('You cannot create the client used in functional tests. Check the error: ' . $e->getMessage());
        }

        $this->client->disableReboot();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, mixed>  $queryParams
     * @param array<string, string> $headers
     */
    protected function makeRequest(
        string $method,
        string $uri,
        string|array $body = [],
        array $queryParams = [],
        array $headers = [],
        ?Uuid $loggedUserId = null,
    ): Response {
        $headers = array_merge(
            $loggedUserId ? ['HTTP_Authorization' => 'Bearer ' . $loggedUserId->value()] : [],
            $headers
        );

        $body = \is_array($body) ? json_encode($body) : $body;
        if (false === $body) {
            $this->fail('Cannot make request');
        }

        $this->client->request(
            method: $method,
            uri: 'http://localhost/api/v1' . $uri,
            parameters: $queryParams,
            files: [],
            server: $headers,
            content: $body,
        );

        return $this->client->getResponse();
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function extractResponseData(Response $response): array
    {
        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertJson($content);

        $responseDecode = json_decode($content, true);
        $this->assertArrayHasKey('data', $responseDecode);

        return $responseDecode['data'];
    }

    protected function extractResponseMessage(Response $response): string
    {
        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertJson($content);

        $responseDecode = json_decode($content, true);
        $this->assertArrayHasKey('message', $responseDecode);

        return $responseDecode['message'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJwtPayload(string $token): array
    {
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT token must have 3 parts');

        $payloadPart = strtr($parts[1], '-_', '+/');
        $payloadPart .= str_repeat('=', (4 - \strlen($payloadPart) % 4) % 4);

        $payload = base64_decode($payloadPart, true);
        $this->assertNotFalse($payload, 'JWT payload must be valid base64');

        $decodedPayload = json_decode($payload, true);
        $this->assertIsArray($decodedPayload);

        return $decodedPayload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractResponseMetadata(Response $response): array
    {
        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertJson($content);

        $responseDecode = json_decode($content, true);
        $this->assertArrayHasKey('metadata', $responseDecode);

        return $responseDecode['metadata'];
    }

    /**
     * @throws SecurityException
     * @throws InfrastructureException
     * @throws InvalidEmailException
     */
    private function restoreDefaultUserState(): void
    {
        $userRepository = $this->getUserRepository();
        $passwordHash = $this->hashPassword(self::DEFAULT_USER_EMAIL, [UserRole::User], self::DEFAULT_USER_PASSWORD);

        $user = new User(
            id: new Uuid(self::DEFAULT_USER_ID),
            email: new Email(self::DEFAULT_USER_EMAIL),
            roles: [UserRole::User],
            passwordHash: $passwordHash,
            createdAt: new \DateTimeImmutable(),
        );

        $userRepository->put($user);
    }

    protected function getUserRepository(): UserRepository
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->getContainer()->get('App\Domain\User\UserRepository');

        return $userRepository;
    }

    /**
     * @param UserRole[] $roles
     *
     * @throws SecurityException
     * @throws InvalidEmailException
     */
    protected function hashPassword(string $email, array $roles, string $plainPassword): string
    {
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $this->getContainer()->get('Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface');

        $securityUser = new SecurityUser(
            id: new Uuid(self::DEFAULT_USER_ID),
            email: new Email($email),
            roles: $roles,
        );

        return $passwordHasher->hashPassword($securityUser, $plainPassword);
    }
}
