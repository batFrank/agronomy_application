<?php

declare(strict_types=1);

namespace App\Application\Authorization\User;

use App\Domain\User\User;
use App\Domain\User\UserRole;

readonly class UserVoter
{
    public static function canView(User $targetUser, User $requestingUser): bool
    {
        if ($requestingUser->hasRole(UserRole::Admin)) {
            return true;
        }

        if ($targetUser->getId()->equals($requestingUser->getId())) {
            return true;
        }

        if ($requestingUser->hasRole(UserRole::Manager)) {
            $createdBy = $targetUser->getCreatedBy();
            if (null !== $createdBy && $createdBy->equals($requestingUser->getId())) {
                return true;
            }
        }

        return false;
    }
}
