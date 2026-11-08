<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Hydrator\User\UserHydratorCollection;
use App\Application\Query\GetUser;
use App\Application\QueryHandler\GetUserQueryHandler;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Hydrator\HydratorException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCollection;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserHydrationCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetUserQueryHandlerTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserHydratorCollection&MockObject $userHydratorCollection;
    private GetUserQueryHandler $handler;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userHydratorCollection = $this->createMock(UserHydratorCollection::class);
        $this->handler = new GetUserQueryHandler($this->userRepository, $this->userHydratorCollection);
    }

    /**
     * @throws InfrastructureException
     * @throws HydratorException
     * @throws UserNotFoundException
     */
    public function testHandleReturnsUserWithoutHydrationByDefault(): void
    {
        $user = UserMother::create();
        $criteria = UserCriteria::createById($user->getId());
        $query = new GetUser(criteria: $criteria, requestingUser: $user);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($criteria)
            ->willReturn($user);

        $this->userHydratorCollection
            ->expects($this->never())
            ->method('hydrate');

        $result = $this->handler->handle($query);

        $this->assertSame($user, $result);
    }

    /**
     * @throws InfrastructureException
     * @throws HydratorException
     * @throws UserNotFoundException
     */
    public function testHandleHydratesCreatorWhenRequested(): void
    {
        $user = UserMother::create();
        $criteria = UserCriteria::createById($user->getId());
        $query = new GetUser(criteria: $criteria, requestingUser: $user, needsCreator: true);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($criteria)
            ->willReturn($user);

        $this->userHydratorCollection
            ->expects($this->once())
            ->method('hydrate')
            ->with(
                $this->callback(function (UserCollection $collection) use ($user): bool {
                    return 1 === $collection->count() && $user === $collection->first();
                }),
                $this->callback(function (UserHydrationCriteria $criteria): bool {
                    return $criteria->shouldHydrateCreator();
                })
            );

        $result = $this->handler->handle($query);

        $this->assertSame($user, $result);
    }

    /**
     * @throws InfrastructureException
     * @throws HydratorException
     * @throws UserNotFoundException
     */
    public function testHandleThrowsUserNotFoundException(): void
    {
        $this->expectException(UserNotFoundException::class);

        $requestingUser = UserMother::create(roles: [UserRole::Admin]);
        $criteria = UserCriteria::createEmpty();
        $query = new GetUser(criteria: $criteria, requestingUser: $requestingUser);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($criteria)
            ->willThrowException(new UserNotFoundException());

        $this->userHydratorCollection
            ->expects($this->never())
            ->method('hydrate');

        $this->handler->handle($query);
    }

    /**
     * @throws InfrastructureException
     * @throws HydratorException
     * @throws UserNotFoundException
     * @throws UserForbiddenException
     */
    #[DataProvider('authorizedAccessProvider')]
    public function testAllowsAuthorizedAccess(
        ?User $requestingUser,
        User $targetUser,
        string $scenario,
    ): void {
        $criteria = UserCriteria::createById($targetUser->getId());
        $query = new GetUser(criteria: $criteria, requestingUser: $requestingUser);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($criteria)
            ->willReturn($targetUser);

        $this->userHydratorCollection
            ->expects($this->never())
            ->method('hydrate');

        $result = $this->handler->handle($query);

        $this->assertSame($targetUser, $result, "Failed scenario: {$scenario}");
    }

    /**
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public static function authorizedAccessProvider(): \Generator
    {
        yield 'Admin can view any user' => [
            'requestingUser' => UserMother::create(roles: [UserRole::Admin]),
            'targetUser' => UserMother::create(),
            'scenario' => 'Admin viewing other user',
        ];

        $user = UserMother::create();
        yield 'User can view themselves' => [
            'requestingUser' => $user,
            'targetUser' => $user,
            'scenario' => 'User viewing themselves',
        ];

        $managerId = UuidMother::create();
        $manager = UserMother::create(id: $managerId, roles: [UserRole::Manager]);
        yield 'Manager can view users they created' => [
            'requestingUser' => $manager,
            'targetUser' => UserMother::create(createdBy: $managerId),
            'scenario' => 'Manager viewing user they created',
        ];

        yield 'No requesting user allows access' => [
            'requestingUser' => null,
            'targetUser' => UserMother::create(),
            'scenario' => 'No requesting user (internal command usage)',
        ];
    }

    /**
     * @throws InfrastructureException
     * @throws HydratorException
     * @throws UserNotFoundException
     */
    #[DataProvider('unauthorizedAccessProvider')]
    public function testThrowsForbiddenForUnauthorizedAccess(
        User $requestingUser,
        User $targetUser,
    ): void {
        $criteria = UserCriteria::createById($targetUser->getId());
        $query = new GetUser(criteria: $criteria, requestingUser: $requestingUser);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($criteria)
            ->willReturn($targetUser);

        $this->userHydratorCollection
            ->expects($this->never())
            ->method('hydrate');

        $this->expectException(UserForbiddenException::class);
        $this->expectExceptionMessage('You are not authorized to view this user');

        $this->handler->handle($query);
    }

    /**
     * @throws InfrastructureException
     * @throws HydratorException
     * @throws UserNotFoundException
     * @throws UserForbiddenException
     */
    public function testDoesNotHydrateCreatorWhenAccessIsForbidden(): void
    {
        $requestingUser = UserMother::create(roles: [UserRole::User]);
        $targetUser = UserMother::create();
        $criteria = UserCriteria::createById($targetUser->getId());
        $query = new GetUser(criteria: $criteria, requestingUser: $requestingUser, needsCreator: true);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($criteria)
            ->willReturn($targetUser);

        $this->userHydratorCollection
            ->expects($this->never())
            ->method('hydrate');

        $this->expectException(UserForbiddenException::class);
        $this->expectExceptionMessage('You are not authorized to view this user');

        $this->handler->handle($query);
    }

    /**
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public static function unauthorizedAccessProvider(): \Generator
    {
        $managerId = UuidMother::create();
        $otherManagerId = UuidMother::create();

        yield 'Manager cannot view users they did not create' => [
            'requestingUser' => UserMother::create(id: $managerId, roles: [UserRole::Manager]),
            'targetUser' => UserMother::create(createdBy: $otherManagerId),
        ];

        yield 'Regular user cannot view other users' => [
            'requestingUser' => UserMother::create(roles: [UserRole::User]),
            'targetUser' => UserMother::create(),
        ];

        yield 'Manager cannot view user with no creator' => [
            'requestingUser' => UserMother::create(roles: [UserRole::Manager]),
            'targetUser' => UserMother::create(createdBy: null),
        ];
    }
}
