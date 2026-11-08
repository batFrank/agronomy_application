<?php

declare(strict_types=1);

namespace App\Tests\Application\Authorization\User;

use App\Application\Authorization\User\UserVoter;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\UserRole;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\TestCase;

class UserVoterTest extends TestCase
{
    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testCanViewReturnsTrueForAdmin(): void
    {
        $admin = UserMother::create(roles: [UserRole::Admin, UserRole::User]);
        $targetUser = UserMother::create();

        $result = UserVoter::canView($targetUser, $admin);

        $this->assertTrue($result);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testCanViewReturnsTrueWhenUserViewsThemselves(): void
    {
        $user = UserMother::create(roles: [UserRole::User]);

        $result = UserVoter::canView($user, $user);

        $this->assertTrue($result);
    }

    /**
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testCanViewReturnsTrueForManagerViewingUserTheyCreated(): void
    {
        $managerId = UuidMother::create();
        $manager = UserMother::create(id: $managerId, roles: [UserRole::Manager, UserRole::User]);
        $createdUser = UserMother::create(createdBy: $managerId);

        $result = UserVoter::canView($createdUser, $manager);

        $this->assertTrue($result);
    }

    /**
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testCanViewReturnsFalseForManagerViewingUserTheyDidNotCreate(): void
    {
        $managerId = UuidMother::create();
        $otherManagerId = UuidMother::create();
        $manager = UserMother::create(id: $managerId, roles: [UserRole::Manager, UserRole::User]);
        $otherUser = UserMother::create(createdBy: $otherManagerId);

        $result = UserVoter::canView($otherUser, $manager);

        $this->assertFalse($result);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testCanViewReturnsFalseForRegularUserViewingOtherUser(): void
    {
        $user = UserMother::create(roles: [UserRole::User]);
        $otherUser = UserMother::create(roles: [UserRole::User]);

        $result = UserVoter::canView($otherUser, $user);

        $this->assertFalse($result);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testCanViewReturnsTrueForManagerViewingUserCreatedByNullWhenManagerIsTarget(): void
    {
        $manager = UserMother::create(roles: [UserRole::Manager, UserRole::User]);
        $userWithNoCreator = UserMother::create(createdBy: null);

        $result = UserVoter::canView($userWithNoCreator, $manager);

        $this->assertFalse($result);
    }
}
