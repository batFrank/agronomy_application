<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Application\Security\User;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\NotFoundException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Domain\User\UserRole;
use App\Infrastructure\AI\Context\AgentContext;
use App\Infrastructure\AI\Toolbox\AgentTool;
use App\Infrastructure\AI\Toolbox\ToolCallException;
use App\Infrastructure\Validator\ValidatorException;
use App\Tests\Infrastructure\AI\HelperTrait\ToolboxHelperTrait;
use App\Tests\Infrastructure\AI\Toolbox\Double\ProbePayload;
use App\Tests\Infrastructure\AI\Toolbox\Double\ProbeTool;
use App\Tests\Mother\Application\Security\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[CoversClass(AgentTool::class)]
#[CoversClass(ToolCallException::class)]
class AgentToolToolboxTest extends KernelTestCase
{
    use ToolboxHelperTrait;

    public static function rejectedArgumentProvider(): \Generator
    {
        yield 'scalar argument' => ['probe', ['city' => 'Zk7']];
        yield 'object argument' => ['probe_payload', ['payload' => ['secretField' => 'Zk7']]];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('rejectedArgumentProvider')]
    public function testValidationErrorDoesNotLeakRejectedValueNorClassNames(string $toolName, array $arguments): void
    {
        // Arrange
        self::bootKernel();
        $toolbox = $this->createToolbox($this->createProbeTool());

        // Act
        $result = $this->callTool($toolbox, $toolName, $arguments);

        // Assert
        self::assertIsString($result);
        self::assertNotSame($this->genericResultFor($toolName), $result);
        self::assertStringNotContainsString('handled:', $result);
        self::assertStringNotContainsString('Zk7', $result);
        self::assertStringNotContainsString(ProbeTool::class, $result);
        self::assertStringNotContainsString(ProbePayload::class, $result);
    }

    public function testDeclaresMinLengthInMetadataAndRejectsShortArgumentBeforeHandler(): void
    {
        // Arrange
        self::bootKernel();
        $toolbox = $this->createToolbox($this->createProbeTool());
        $probe = $this->findTool($toolbox, 'probe');

        // Act
        $result = $this->callTool($toolbox, 'probe', ['city' => 'Bil']);

        // Assert
        self::assertSame(5, $probe->getParameters()['properties']['city']['minLength'] ?? null);
        self::assertIsString($result);
        self::assertStringContainsString('city', $result);
        self::assertStringNotContainsString('handled:', $result);
    }

    public function testReturnsGenericMessageWhenBusFailsWithUnknownException(): void
    {
        // Arrange
        self::bootKernel();
        $toolbox = $this->createToolbox($this->createProbeTool(
            new \RuntimeException('SQLSTATE[42P01] relation secret_table is missing'),
        ));

        // Act
        $result = $this->callTool($toolbox, 'probe', ['city' => 'Bilbao']);

        // Assert
        self::assertSame('The operation could not be completed.', $result);
        self::assertStringNotContainsString('secret_table', (string) $result);
    }

    public function testReturnsGenericMessageWhenGuardedToolHasNoToken(): void
    {
        // Arrange
        self::bootKernel();
        $toolbox = $this->createToolbox($this->createProbeTool());

        // Act
        $result = $this->callTool($toolbox, 'probe_guarded', ['city' => 'Bilbao']);

        // Assert
        self::assertSame($this->genericResultFor('probe_guarded'), $result);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public function testReturnsGenericMessageWhenGuardedToolIsCalledWithoutRequiredRole(): void
    {
        // Arrange
        self::bootKernel();
        $this->setToken(UserMother::create(roles: [UserRole::User]));
        $toolbox = $this->createToolbox($this->createProbeTool());

        // Act
        $result = $this->callTool($toolbox, 'probe_guarded', ['city' => 'Bilbao']);

        // Assert
        self::assertSame($this->genericResultFor('probe_guarded'), $result);
        self::assertStringNotContainsString('handled:', (string) $result);
    }

    public function testReturnsGenericMessageWhenActorIsMissingFromContext(): void
    {
        // Arrange
        self::bootKernel();
        $toolbox = $this->createToolbox($this->createProbeTool());

        // Act
        $result = $this->callTool($toolbox, 'probe_actor');

        // Assert
        self::assertSame($this->genericResultFor('probe_actor'), $result);
    }

    #[DataProvider('domainFailureProvider')]
    public function testDomainExceptionFromBusReachesModelAsActionableMessage(\Throwable $failure, string $expected): void
    {
        // Arrange
        self::bootKernel();
        $toolbox = $this->createToolbox($this->createProbeTool($failure));

        // Act
        $result = $this->callTool($toolbox, 'probe', ['city' => 'Bilbao']);

        // Assert
        self::assertSame($expected, $result);
    }

    public static function domainFailureProvider(): \Generator
    {
        yield 'not found' => [
            new NotFoundException('User 123 not found'),
            'The requested resource was not found. Reason: User 123 not found',
        ];

        yield 'forbidden' => [
            UserForbiddenException::cannotViewUser(),
            'The current user is not allowed to perform this operation. Reason: You are not authorized to view this user',
        ];

        yield 'unprocessable entity' => [
            new InvalidEmailException('not-an-email'),
            'The operation was rejected by a business rule. Reason: Invalid email address: not-an-email',
        ];

        yield 'validator' => [
            ValidatorException::createByViolations(new ConstraintViolationList([
                new ConstraintViolation('This value is too short.', null, [], null, 'city', 'Bil'),
            ])),
            "The input is not valid.\ncity: This value is too short.",
        ];
    }

    public function testReturnsHandlerResultWhenNoFailureOccurs(): void
    {
        // Arrange
        self::bootKernel();
        $toolbox = $this->createToolbox($this->createProbeTool());

        // Act
        $result = $this->callTool($toolbox, 'probe', ['city' => 'Bilbao']);

        // Assert
        self::assertSame('handled:Bilbao', $result);
    }

    public function testGuardedToolReachesHandlerWhenRoleIsAdmin(): void
    {
        // Arrange
        self::bootKernel();
        $this->setToken(UserMother::create(roles: [UserRole::Admin]));
        $toolbox = $this->createToolbox($this->createProbeTool());

        // Act
        $result = $this->callTool($toolbox, 'probe_guarded', ['city' => 'Bilbao']);

        // Assert
        self::assertSame('handled:Bilbao', $result);
    }

    public function testActorToolReachesHandlerWhenActorIsEntered(): void
    {
        // Arrange
        self::bootKernel();
        $user = UserMother::create();
        $context = self::getContainer()->get('test.agent_context');
        self::assertInstanceOf(AgentContext::class, $context);
        $toolbox = $this->createToolbox($this->createProbeTool());

        // Act
        $context->enterAs($user);
        try {
            $result = $this->callTool($toolbox, 'probe_actor');
        } finally {
            $context->leave();
        }

        // Assert
        self::assertSame('handled:' . $user->getUserIdentifier(), $result);
    }

    private function genericResultFor(string $toolName): string
    {
        return ToolExecutionException::executionFailed(
            new ToolCall('generic', $toolName, []),
            new \RuntimeException(),
        )->getToolCallResult();
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    private function setToken(User $user): void
    {
        $tokenStorage = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function findTool(ToolboxInterface $toolbox, string $name): Tool
    {
        foreach ($toolbox->getTools() as $tool) {
            if ($name === $tool->getName()) {
                return $tool;
            }
        }

        self::fail(\sprintf('Tool "%s" is not registered in the toolbox.', $name));
    }
}
