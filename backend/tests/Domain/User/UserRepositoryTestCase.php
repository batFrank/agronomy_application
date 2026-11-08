<?php

declare(strict_types=1);

namespace App\Tests\Domain\User;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\UserCollection;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

abstract class UserRepositoryTestCase extends TestCase
{
    abstract protected function getRepository(): UserRepository;

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws UserNotFoundException
     */
    public function testPutAndFindOneBy(): void
    {
        $repository = $this->getRepository();
        $user = UserMother::create();

        $repository->put($user);

        $this->assertEquals(
            $user,
            $repository->findOneBy(
                UserCriteria::createById($user->getId())
            )
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws UserNotFoundException
     */
    public function testFindOneByThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $repository = $this->getRepository();
        $user = UserMother::create();

        $repository->findOneBy(
            UserCriteria::createById($user->getId())
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws CollectionException
     * @throws InvalidUuidException
     */
    #[DataProvider('findByFiltersProvider')]
    public function testFindBy(
        UserCollection $users,
        UserCriteria $criteria,
        int $expectedCount,
        UserCollection $expectedUsers,
    ): void {
        $repository = $this->getRepository();

        foreach ($users->all() as $user) {
            $repository->put($user);
        }

        $result = $repository->findBy($criteria);

        $this->assertCount($expectedCount, $result->all());

        $resultIds = array_map(fn ($user) => $user->getId()->value(), $result->all());
        $expectedIds = array_map(fn ($user) => $user->getId()->value(), $expectedUsers->all());

        sort($resultIds);
        sort($expectedIds);

        $this->assertEquals($expectedIds, $resultIds);
    }

    /**
     * @return \Generator<string, array{UserCollection, UserCriteria, int, UserCollection}>
     *
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public static function findByFiltersProvider(): \Generator
    {
        $manager1Uuid = UuidMother::create();
        $manager2Uuid = UuidMother::create();

        $user1 = UserMother::create(
            email: new Email('user1@example.com'),
            roles: [UserRole::User],
        );
        $user2 = UserMother::create(
            email: new Email('user2@example.com'),
            roles: [UserRole::Admin],
        );

        yield 'no filter returns all users' => [
            new UserCollection([$user1, $user2]),
            UserCriteria::createEmpty(),
            2,
            new UserCollection([$user1, $user2]),
        ];

        yield 'filter by search string' => [
            new UserCollection([$user1, $user2]),
            UserCriteria::createEmpty()->filterBySearchString('1'),
            1,
            new UserCollection([$user1]),
        ];

        yield 'filter by role' => [
            new UserCollection([$user1, $user2]),
            UserCriteria::createEmpty()->filterByRole(UserRole::Admin),
            1,
            new UserCollection([$user2]),
        ];

        $userByManager1 = UserMother::create(
            email: new Email('user.m1@example.com'),
            roles: [UserRole::User],
            createdBy: $manager1Uuid,
        );
        $userByManager2 = UserMother::create(
            email: new Email('user.m2@example.com'),
            roles: [UserRole::User],
            createdBy: $manager2Uuid,
        );
        $userWithoutCreatedBy = UserMother::create(
            email: new Email('user.none@example.com'),
            roles: [UserRole::User],
        );

        yield 'filter by createdBy' => [
            new UserCollection([$userByManager1, $userByManager2, $userWithoutCreatedBy]),
            UserCriteria::createEmpty()->filterByCreatedBy($manager1Uuid),
            1,
            new UserCollection([$userByManager1]),
        ];

        $user1ByManager1 = UserMother::create(
            email: new Email('user1.m1@example.com'),
            roles: [UserRole::User],
            createdBy: $manager1Uuid,
        );
        $user2ByManager1 = UserMother::create(
            email: new Email('user2.m1@example.com'),
            roles: [UserRole::User],
            createdBy: $manager1Uuid,
        );
        $userByManager2_2 = UserMother::create(
            email: new Email('user.m2.2@example.com'),
            roles: [UserRole::User],
            createdBy: $manager2Uuid,
        );

        yield 'filter by createdBy with multiple users' => [
            new UserCollection([$user1ByManager1, $user2ByManager1, $userByManager2_2]),
            UserCriteria::createEmpty()->filterByCreatedBy($manager1Uuid),
            2,
            new UserCollection([$user1ByManager1, $user2ByManager1]),
        ];

        $adminByManager = UserMother::create(
            email: new Email('admin.m1@example.com'),
            roles: [UserRole::Admin],
            createdBy: $manager1Uuid,
        );
        $userByManager = UserMother::create(
            email: new Email('user.m1.3@example.com'),
            roles: [UserRole::User],
            createdBy: $manager1Uuid,
        );
        $adminNotByManager = UserMother::create(
            email: new Email('admin.none@example.com'),
            roles: [UserRole::Admin],
        );

        yield 'filter by createdBy and role combined' => [
            new UserCollection([$adminByManager, $userByManager, $adminNotByManager]),
            UserCriteria::createEmpty()
                ->filterByCreatedBy($manager1Uuid)
                ->filterByRole(UserRole::Admin),
            1,
            new UserCollection([$adminByManager]),
        ];

        $alice = UserMother::create(
            email: new Email('alice@example.com'),
            roles: [UserRole::User],
            createdBy: $manager1Uuid,
        );
        $bob = UserMother::create(
            email: new Email('bob@example.com'),
            roles: [UserRole::User],
            createdBy: $manager1Uuid,
        );
        $aliceOther = UserMother::create(
            email: new Email('alice@other.com'),
            roles: [UserRole::User],
            createdBy: $manager2Uuid,
        );

        yield 'filter by createdBy and search string combined' => [
            new UserCollection([$alice, $bob, $aliceOther]),
            UserCriteria::createEmpty()
                ->filterByCreatedBy($manager1Uuid)
                ->filterBySearchString('alice'),
            1,
            new UserCollection([$alice]),
        ];
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testFindByEmail(): void
    {
        $email = new Email('specific@example.com');
        $user = UserMother::create(email: $email);

        $repository = $this->getRepository();
        $repository->put($user);
        $repository->put(UserMother::create());

        $this->assertCount(
            1,
            $repository->findBy(
                UserCriteria::createEmpty()
                    ->filterByEmail($email)
            )->all()
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testCountBy(): void
    {
        $repository = $this->getRepository();
        $repository->put(UserMother::create(roles: [UserRole::User]));
        $repository->put(UserMother::create(roles: [UserRole::Admin]));
        $repository->put(UserMother::create(roles: [UserRole::User]));

        $this->assertEquals(
            3,
            $repository->countBy(UserCriteria::createEmpty())
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testFindByPasswordResetToken(): void
    {
        $token = 'reset_token_123';
        $user = UserMother::create(passwordResetToken: $token);

        $repository = $this->getRepository();
        $repository->put($user);
        $repository->put(UserMother::create());

        $this->assertCount(
            1,
            $repository->findBy(
                UserCriteria::createEmpty()
                    ->filterByPasswordResetToken($token)
            )->all()
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testFindByPasswordResetTokenExpiresBefore(): void
    {
        $repository = $this->getRepository();
        $repository->put(UserMother::create(passwordResetTokenExpiration: new \DateTimeImmutable('-1 day')));
        $repository->put(UserMother::create(passwordResetTokenExpiration: new \DateTimeImmutable('+1 day')));

        $this->assertCount(
            1,
            $repository->findBy(
                UserCriteria::createEmpty()
                    ->filterByPasswordResetTokenExpiresBefore(new \DateTimeImmutable())
            )->all()
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws UserNotFoundException
     */
    public function testPutAndFindUserWithCreatedBy(): void
    {
        $repository = $this->getRepository();
        $createdByUuid = UuidMother::create();
        $user = UserMother::create(createdBy: $createdByUuid);

        $repository->put($user);

        $foundUser = $repository->findOneBy(
            UserCriteria::createById($user->getId())
        );

        $this->assertTrue($foundUser->getCreatedBy()?->equals($createdByUuid));
    }
}
