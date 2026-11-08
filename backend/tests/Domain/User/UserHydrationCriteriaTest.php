<?php

declare(strict_types=1);

namespace App\Tests\Domain\User;

use App\Domain\User\UserHydrationCriteria;
use PHPUnit\Framework\TestCase;

class UserHydrationCriteriaTest extends TestCase
{
    public function testShouldNotHydrateCreatorByDefault(): void
    {
        $criteria = UserHydrationCriteria::createEmpty();

        $this->assertFalse($criteria->shouldHydrateCreator());
    }

    public function testWithCreatorEnablesCreatorHydration(): void
    {
        $criteria = UserHydrationCriteria::createEmpty()->withCreator();

        $this->assertTrue($criteria->shouldHydrateCreator());
    }
}
