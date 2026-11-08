<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent\UserDirectory;

use App\Application\Security\User as SecurityUser;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Infrastructure\AI\Context\AgentContext;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Assert;

/**
 * Three people in the in-memory repository: an admin, a manager created by the admin and a user
 * created by the manager. The actor of each test enters the AgentContext as the test, because the
 * boundary that fills it in production (controller, command) belongs to another issue.
 */
trait UserDirectoryScenarioTrait
{
    private User $admin;
    private User $manager;
    private User $member;

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    private function givenTheDirectory(): void
    {
        $repository = self::getContainer()->get(UserRepository::class);
        Assert::assertInstanceOf(UserRepository::class, $repository);

        $this->admin = UserMother::create(
            email: EmailMother::create('admin@example.com'),
            roles: [UserRole::Admin, UserRole::User],
            createdAt: new \DateTimeImmutable('2026-01-01 09:00:00'),
        );
        $this->manager = UserMother::create(
            email: EmailMother::create('manager@example.com'),
            roles: [UserRole::Manager, UserRole::User],
            createdAt: new \DateTimeImmutable('2026-01-02 09:00:00'),
            createdBy: $this->admin->getId(),
        );
        $this->member = UserMother::create(
            email: EmailMother::create('member@example.com'),
            roles: [UserRole::User],
            createdAt: new \DateTimeImmutable('2026-01-03 09:00:00'),
            createdBy: $this->manager->getId(),
        );

        foreach ([$this->admin, $this->manager, $this->member] as $user) {
            $repository->put($user);
        }
    }

    private function givenTheActorIs(User $user): void
    {
        $context = self::getContainer()->get('test.agent_context');
        Assert::assertInstanceOf(AgentContext::class, $context);

        $context->enterAs(SecurityUser::createFromDomainUser($user));
    }

    private function leaveTheContext(): void
    {
        $context = self::getContainer()->get('test.agent_context');
        Assert::assertInstanceOf(AgentContext::class, $context);

        $context->leave();
    }

    /**
     * @return list<string> every identifier of the scenario, as the model must never see them
     */
    private function everyIdentifier(): array
    {
        return array_map(
            static fn (User $user): string => $user->getId()->value(),
            [$this->admin, $this->manager, $this->member],
        );
    }
}
