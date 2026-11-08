<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent\UserDirectory;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Infrastructure\AI\Agent\UserDirectory\ListUsersTool;
use App\Infrastructure\AI\Context\AgentContext;
use App\Infrastructure\AI\Toolbox\IdentifierStrippingToolbox;
use App\Infrastructure\Transformer\DateTimeTransformer;
use App\Tests\Infrastructure\AI\HelperTrait\ToolboxHelperTrait;
use App\Tests\Mother\Application\Security\UserMother as SecurityUserMother;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every test goes through `ai.toolbox.user_directory`, the service the bundle builds from ai.yaml
 * and that StripIdentifiersFromToolboxesPass decorates: the wiring of the identifier stripping is
 * verified on a real toolbox, not on the sanitizer.
 */
#[CoversClass(ListUsersTool::class)]
#[CoversClass(IdentifierStrippingToolbox::class)]
class ListUsersToolboxTest extends KernelTestCase
{
    use ToolboxHelperTrait;
    use UserDirectoryScenarioTrait;

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->givenTheDirectory();
    }

    protected function tearDown(): void
    {
        $this->leaveTheContext();

        parent::tearDown();
    }

    public function testIdentifierStrippingToolboxDecoratesTheToolboxBuiltFromAiYaml(): void
    {
        $toolbox = $this->userDirectoryToolbox();

        self::assertInstanceOf(IdentifierStrippingToolbox::class, $toolbox);
    }

    public function testExposesOnlyTheToolListedInAiYaml(): void
    {
        $names = array_map(
            static fn (Tool $tool): string => $tool->getName(),
            $this->userDirectoryToolbox()->getTools(),
        );

        self::assertSame(['list_users'], $names);
    }

    public function testAdminReceivesEveryUserWithoutAnyIdentifier(): void
    {
        // Arrange
        $this->givenTheActorIs($this->admin);

        // Act
        $result = $this->callTool($this->userDirectoryToolbox(), 'list_users');

        // Assert
        self::assertSame(json_encode([
            'users' => [
                [
                    'email' => 'admin@example.com',
                    'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
                    'created_at' => DateTimeTransformer::toOutput(new \DateTimeImmutable('2026-01-01 09:00:00')),
                    'updated_at' => null,
                    'created_by' => null,
                ],
                [
                    'email' => 'manager@example.com',
                    'roles' => ['ROLE_MANAGER', 'ROLE_USER'],
                    'created_at' => DateTimeTransformer::toOutput(new \DateTimeImmutable('2026-01-02 09:00:00')),
                    'updated_at' => null,
                    'creator' => ['email' => 'admin@example.com'],
                ],
                [
                    'email' => 'member@example.com',
                    'roles' => ['ROLE_USER'],
                    'created_at' => DateTimeTransformer::toOutput(new \DateTimeImmutable('2026-01-03 09:00:00')),
                    'updated_at' => null,
                    'creator' => ['email' => 'manager@example.com'],
                ],
            ],
            'total' => 3,
            'page' => 1,
            'page_size' => 10,
        ]), $result);
        $this->assertNoIdentifierIn($result);
    }

    public function testManagerOnlyReceivesTheUsersTheyCreated(): void
    {
        // Arrange
        $this->givenTheActorIs($this->manager);

        // Act
        $result = $this->callTool($this->userDirectoryToolbox(), 'list_users');

        // Assert
        $payload = json_decode((string) $result, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['member@example.com'], array_column($payload['users'], 'email'));
        self::assertSame(1, $payload['total']);
        $this->assertNoIdentifierIn($result);
    }

    public function testFiltersByRoleAndSearchText(): void
    {
        // Arrange
        $this->givenTheActorIs($this->admin);

        // Act
        $result = $this->callTool($this->userDirectoryToolbox(), 'list_users', ['role' => 'ROLE_MANAGER', 'search' => 'manager']);

        // Assert
        $payload = json_decode((string) $result, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['manager@example.com'], array_column($payload['users'], 'email'));
    }

    public function testSecondPageHoldsWhatDoesNotFitInTheFirst(): void
    {
        // Arrange: 3 users from the scenario plus 9 more, 12 in total with 10 per page.
        $repository = self::getContainer()->get(UserRepository::class);
        Assert::assertInstanceOf(UserRepository::class, $repository);
        for ($i = 1; $i <= 9; ++$i) {
            $repository->put(UserMother::create(
                email: EmailMother::create(\sprintf('extra%d@example.com', $i)),
                roles: [UserRole::User],
                createdBy: $this->admin->getId(),
            ));
        }
        $this->givenTheActorIs($this->admin);
        $toolbox = $this->userDirectoryToolbox();

        // Act
        $first = json_decode((string) $this->callTool($toolbox, 'list_users'), true, flags: \JSON_THROW_ON_ERROR);
        $second = json_decode((string) $this->callTool($toolbox, 'list_users', ['page' => 2]), true, flags: \JSON_THROW_ON_ERROR);

        // Assert
        self::assertCount(10, $first['users']);
        self::assertSame(['total' => 12, 'page' => 1, 'page_size' => 10], array_diff_key($first, ['users' => 1]));
        self::assertSame(['extra8@example.com', 'extra9@example.com'], array_column($second['users'], 'email'));
        self::assertSame(['total' => 12, 'page' => 2, 'page_size' => 10], array_diff_key($second, ['users' => 1]));
    }

    /**
     * The arguments are validated by the #[Schema] of the tool (the SDK argument resolver applies it),
     * not by a Validator: whoever copies the template gets this for free, and an SDK update that
     * stops applying it fails here. The message names the argument and never reaches the handler.
     *
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('rejectedArgumentProvider')]
    public function testRejectsAnArgumentTheSchemaForbidsBeforeTheHandlerRuns(array $arguments, string $argument): void
    {
        // Arrange
        $this->givenTheActorIs($this->admin);

        // Act
        $result = $this->callTool($this->userDirectoryToolbox(), 'list_users', $arguments);

        // Assert
        self::assertIsString($result);
        self::assertStringContainsString($argument, $result);
        self::assertStringNotContainsString('@example.com', $result);
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, string}>
     */
    public static function rejectedArgumentProvider(): \Generator
    {
        yield 'role outside the enum' => [['role' => 'ROLE_SUPERADMIN'], 'role'];
        yield 'page zero' => [['page' => 0], 'page'];
        yield 'negative page' => [['page' => -3], 'page'];
        yield 'empty search, as the HTTP endpoint rejects it' => [['search' => ''], 'search'];
    }

    public function testRefusesAPersonWithoutAdminNorManagerRole(): void
    {
        // Arrange
        $this->givenTheActorIs($this->member);

        // Act
        $result = $this->callTool($this->userDirectoryToolbox(), 'list_users');

        // Assert
        self::assertSame('The current user is not allowed to list users.', $result);
        $this->assertNoIdentifierIn($result);
        self::assertStringNotContainsString('member@example.com', (string) $result);
    }

    public function testAnsweringWithoutActorGivesTheGenericMessageAndListsNothing(): void
    {
        // Act
        $result = $this->callTool($this->userDirectoryToolbox(), 'list_users');

        // Assert
        self::assertSame('An error occurred while executing tool "list_users".', $result);
    }

    public function testFailsInsteadOfListingEverybodyWhenTheActorHasNoDomainUser(): void
    {
        // Arrange: a manager that was not built from a domain user cannot be scoped by the handler.
        $context = self::getContainer()->get('test.agent_context');
        Assert::assertInstanceOf(AgentContext::class, $context);
        $context->enterAs(SecurityUserMother::create(roles: [UserRole::Manager]));

        // Act
        $result = $this->callTool($this->userDirectoryToolbox(), 'list_users');

        // Assert
        self::assertSame('An error occurred while executing tool "list_users".', $result);
        self::assertStringNotContainsString('@example.com', (string) $result);
    }

    private function userDirectoryToolbox(): ToolboxInterface
    {
        $toolbox = self::getContainer()->get('test.ai.toolbox.user_directory');
        Assert::assertInstanceOf(ToolboxInterface::class, $toolbox);

        return $toolbox;
    }

    private function assertNoIdentifierIn(?string $result): void
    {
        foreach ($this->everyIdentifier() as $identifier) {
            self::assertStringNotContainsString($identifier, (string) $result);
        }
    }
}
