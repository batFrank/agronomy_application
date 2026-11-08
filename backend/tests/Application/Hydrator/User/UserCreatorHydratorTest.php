<?php

declare(strict_types=1);

namespace App\Tests\Application\Hydrator\User;

use App\Application\Hydrator\HydratorPriority;
use App\Application\Hydrator\User\UserCreatorHydrator;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Hydrator\HydratorException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Shared\Uuid\UuidCollection;
use App\Domain\User\UserCollection;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserHydrationCriteria;
use App\Domain\User\UserRepository;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\TestCase;

class UserCreatorHydratorTest extends TestCase
{
    private function getHydrator(?UserRepository $userRepository = null): UserCreatorHydrator
    {
        return new UserCreatorHydrator(userRepository: $userRepository ?? $this->createStub(UserRepository::class));
    }

    public function testSupportsOnlyCreatorHydrationCriteria(): void
    {
        $hydrator = $this->getHydrator();

        $this->assertFalse($hydrator->supports(UserHydrationCriteria::createEmpty()));
        $this->assertTrue($hydrator->supports(UserHydrationCriteria::createEmpty()->withCreator()));
    }

    public function testReturnsFirstPriority(): void
    {
        $hydrator = $this->getHydrator();

        $this->assertSame(HydratorPriority::First, $hydrator->getPriority());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws HydratorException
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testHydrateLoadsCreatorsInSingleBatchQuery(): void
    {
        $creator = UserMother::create();
        $user1 = UserMother::create(createdBy: $creator->getId());
        $user2 = UserMother::create(createdBy: $creator->getId());
        $userWithoutCreator = UserMother::create(createdBy: null);

        $users = new UserCollection([$user1, $user2, $userWithoutCreator]);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findBy')
            ->with($this->callback(function (UserCriteria $criteria) use ($creator): bool {
                $ids = $criteria->getIds();

                if (!$ids instanceof UuidCollection) {
                    return false;
                }

                /** @var Uuid $firstId */
                $firstId = $ids->first();

                return 1 === $ids->count()
                    && $creator->getId()->equals($firstId);
            }))
            ->willReturn(new UserCollection([$creator]));

        $hydrator = $this->getHydrator(userRepository: $userRepository);

        $hydrator->hydrate($users, UserHydrationCriteria::createEmpty()->withCreator());

        $this->assertNotNull($user1->getCreator());
        $this->assertNotNull($user2->getCreator());
        $this->assertTrue($creator->getUserCard()->getId()->equals($user1->getCreator()->getId()));
        $this->assertTrue($creator->getUserCard()->getId()->equals($user2->getCreator()->getId()));
        $this->assertNull($userWithoutCreator->getCreator());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws HydratorException
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testHydrateDoesNotQueryWhenNoUsersHaveCreator(): void
    {
        $users = new UserCollection([
            UserMother::create(createdBy: null),
            UserMother::create(createdBy: null),
        ]);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->never())
            ->method('findBy');

        $hydrator = $this->getHydrator(userRepository: $userRepository);

        $hydrator->hydrate($users, UserHydrationCriteria::createEmpty()->withCreator());

        $this->assertNull($users->first()?->getCreator());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws HydratorException
     * @throws CollectionException
     */
    public function testHydrateIgnoresMissingCreators(): void
    {
        $missingCreatorId = UuidMother::create();
        $users = new UserCollection([
            UserMother::create(createdBy: $missingCreatorId),
        ]);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new UserCollection([]));

        $hydrator = $this->getHydrator(userRepository: $userRepository);

        $hydrator->hydrate($users, UserHydrationCriteria::createEmpty()->withCreator());

        $this->assertNull($users->first()?->getCreator());
    }
}
