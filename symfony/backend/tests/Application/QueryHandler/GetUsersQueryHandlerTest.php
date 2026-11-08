<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Hydrator\User\UserHydratorCollection;
use App\Application\Query\GetUsers;
use App\Application\QueryHandler\GetUsersQueryHandler;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\Pagination;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Hydrator\HydratorException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Domain\User\User;
use App\Domain\User\UserCollection;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserHydrationCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GetUsersQueryHandlerTest extends TestCase
{
    private function getHandler(
        ?UserRepository $userRepository = null,
        ?UserHydratorCollection $userHydratorCollection = null,
    ): GetUsersQueryHandler {
        return new GetUsersQueryHandler(
            userRepository: $userRepository ?? $this->createMock(UserRepository::class),
            userHydratorCollection: $userHydratorCollection ?? $this->createStub(UserHydratorCollection::class),
        );
    }

    /**
     * @throws InfrastructureException
     * @throws HydratorException
     * @throws CollectionException
     * @throws InvalidUuidException
     */
    public function testThrowsForbiddenExceptionWhenManagerNoAdminTriesToFilterByOtherCreatedBy(): void
    {
        $managerId = UuidMother::create();
        $otherUserId = UuidMother::create();
        $managerUser = UserMother::create(id: $managerId, roles: [UserRole::Manager, UserRole::User]);

        $query = new GetUsers(
            criteria: UserCriteria::createEmpty()->filterByCreatedBy($otherUserId),
            requestingUser: $managerUser,
            needsCreator: true,
        );

        $this->expectExceptionObject(UserForbiddenException::managerOnlyAllowedToSeeOwnUsers());

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->never())->method('findBy');
        $userRepository->expects($this->never())->method('countBy');

        $userHydratorCollection = $this->createMock(UserHydratorCollection::class);
        $userHydratorCollection->expects($this->never())->method('hydrate');

        $handler = $this->getHandler($userRepository, $userHydratorCollection);
        $handler->handle($query);
    }

    /**
     * @throws InfrastructureException
     * @throws CollectionException
     * @throws HydratorException
     */
    public function testDoesNotHydrateCreatorWhenNeedsCreatorIsFalse(): void
    {
        $users = new UserCollection([UserMother::create()]);
        $query = new GetUsers(criteria: UserCriteria::createEmpty(), needsCreator: false);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())->method('findBy')->willReturn($users);
        $userRepository->expects($this->once())->method('countBy')->willReturn(1);

        $userHydratorCollection = $this->createMock(UserHydratorCollection::class);
        $userHydratorCollection->expects($this->never())->method('hydrate');

        $handler = $this->getHandler($userRepository, $userHydratorCollection);
        $handler->handle($query);
    }

    /**
     * @throws InfrastructureException
     * @throws CollectionException
     * @throws HydratorException
     */
    public function testHydratesCreatorWhenNeedsCreatorIsTrueUsingFullCollection(): void
    {
        $users = new UserCollection([
            UserMother::create(),
            UserMother::create(),
            UserMother::create(),
        ]);

        $query = new GetUsers(criteria: UserCriteria::createEmpty(), needsCreator: true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())->method('findBy')->willReturn($users);
        $userRepository->expects($this->once())->method('countBy')->willReturn(3);

        $userHydratorCollection = $this->createMock(UserHydratorCollection::class);
        $userHydratorCollection
            ->expects($this->once())
            ->method('hydrate')
            ->with(
                $this->identicalTo($users),
                $this->callback(
                    fn (UserHydrationCriteria $criteria): bool => $criteria->shouldHydrateCreator()
                )
            );

        $handler = $this->getHandler($userRepository, $userHydratorCollection);
        $result = $handler->handle($query);

        $this->assertSame(3, $result->getTotalItems());
        $this->assertSame($users, $result->getCollection());
    }

    /**
     * @throws InfrastructureException
     * @throws CollectionException
     */
    public function testHandleEmptyCriteria(): void
    {
        $query = new GetUsers(
            criteria: UserCriteria::createEmpty(),
        );

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new UserCollection([]));

        $userRepository
            ->expects($this->once())
            ->method('countBy')
            ->willReturn(0);

        $handler = $this->getHandler($userRepository);
        $handler->handle($query);
    }

    /**
     * @throws InfrastructureException
     * @throws CollectionException
     */
    public function testHandleWithUsers(): void
    {
        $user1 = UserMother::create();
        $user2 = UserMother::create();
        $user3 = UserMother::create();

        $users = new UserCollection([
            $user1,
            $user2,
            $user3,
        ]);

        $query = new GetUsers(
            criteria: UserCriteria::createEmpty(),
        );

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn($users);

        $userRepository
            ->expects($this->once())
            ->method('countBy')
            ->willReturn($users->count());

        $handler = $this->getHandler($userRepository);
        $result = $handler->handle($query);

        $this->assertSame($users->count(), $result->getTotalItems());
        $this->assertSame($users, $result->getCollection());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testHandleWithPagination(): void
    {
        $user1 = UserMother::create();
        $user2 = UserMother::create();

        $paginatedUsers = new UserCollection([
            $user1,
            $user2,
        ]);

        $totalUsers = 3;

        $query = new GetUsers(
            criteria: UserCriteria::createEmpty()
                ->withPagination(new Pagination(1, 2)),
        );

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn($paginatedUsers);

        $userRepository
            ->expects($this->once())
            ->method('countBy')
            ->willReturn($totalUsers);

        $handler = $this->getHandler($userRepository);
        $result = $handler->handle($query);

        $this->assertSame($totalUsers, $result->getTotalItems());
        $this->assertCount(2, $result->getCollection()->all());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    #[DataProvider('provideCriteria')]
    public function testHandleWithCriteria(UserCollection $users, UserCriteria $criteria, int $expectedCount): void
    {
        $query = new GetUsers(
            criteria: $criteria,
        );

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn($users);
        $userRepository
            ->expects($this->once())
            ->method('countBy')
            ->willReturn($expectedCount);

        $handler = $this->getHandler($userRepository);
        $result = $handler->handle($query);

        $this->assertSame($expectedCount, $result->getTotalItems());
        $this->assertCount($expectedCount, $result->getCollection()->all());
    }

    /**
     * @return array <string, array{users: UserCollection, criteria: UserCriteria, expectedCount: int}>
     *
     * @throws CollectionException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public static function provideCriteria(): array
    {
        $user1 = UserMother::create(email: EmailMother::create('user1@example.com'), roles: [UserRole::User]);
        $user2 = UserMother::create(email: EmailMother::create('user2@example.com'), roles: [UserRole::Admin]);
        $user3 = UserMother::create(email: EmailMother::create('user3@example.com'), roles: [UserRole::User]);

        $users = new UserCollection([
            $user1,
            $user2,
            $user3,
        ]);

        return [
            'empty criteria' => [
                'users' => $users,
                'criteria' => UserCriteria::createEmpty(),
                'expectedCount' => 3,
            ],
            'search string' => [
                'users' => new UserCollection([$user1]),
                'criteria' => UserCriteria::createEmpty()
                    ->filterBySearchString('user1'),
                'expectedCount' => 1,
            ],
            'role' => [
                'users' => new UserCollection([$user1, $user3]),
                'criteria' => UserCriteria::createEmpty()
                    ->filterByRole(UserRole::User),
                'expectedCount' => 2,
            ],
        ];
    }

    /**
     * @throws InfrastructureException
     * @throws CollectionException
     * @throws InvalidUuidException
     */
    public function testAutoScopesResultsWhenManagerNoAdminRequestsUsers(): void
    {
        $managerId = UuidMother::create();
        $managerUser = UserMother::create(id: $managerId, roles: [UserRole::Manager, UserRole::User]);
        $userCreatedByManager = UserMother::create(createdBy: $managerId);

        $managerOnlyUsers = new UserCollection([$userCreatedByManager]);

        $query = new GetUsers(
            criteria: UserCriteria::createEmpty(),
            requestingUser: $managerUser,
        );

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->callback(fn (UserCriteria $criteria) => $criteria->getCreatedBy() && $criteria->getCreatedBy()->equals($managerId))
            )
            ->willReturn($managerOnlyUsers);

        $userRepository
            ->expects($this->once())
            ->method('countBy')
            ->willReturn(1);

        $handler = $this->getHandler($userRepository);
        $result = $handler->handle($query);

        $this->assertSame(1, $result->getTotalItems());
    }

    /**
     * @throws InfrastructureException
     * @throws CollectionException
     * @throws InvalidUuidException
     */
    #[DataProvider('provideRequestingUser')]
    public function testAutoScopesNotAppliedWhenAdminRequestsUsers(?User $requestingUser = null): void
    {
        $query = new GetUsers(
            criteria: UserCriteria::createEmpty(),
            requestingUser: $requestingUser,
        );

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->callback(fn (UserCriteria $criteria) => null === $criteria->getCreatedBy())
            )
            ->willReturn(new UserCollection([]));

        $userRepository
            ->expects($this->once())
            ->method('countBy')
            ->willReturn(0);

        $handler = $this->getHandler($userRepository);
        $handler->handle($query);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public static function provideRequestingUser(): \Generator
    {
        yield 'no user' => [
            'requestingUser' => null,
        ];

        yield 'admin user' => [
            'requestingUser' => UserMother::create(roles: [UserRole::Admin, UserRole::User]),
        ];

        yield 'manager user that is also admin' => [
            'requestingUser' => UserMother::create(roles: [UserRole::Manager, UserRole::Admin]),
        ];
    }
}
