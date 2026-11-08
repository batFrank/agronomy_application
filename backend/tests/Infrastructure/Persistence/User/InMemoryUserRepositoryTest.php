<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\User;

use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\User\InMemoryUserRepository;
use App\Tests\Domain\User\UserRepositoryTestCase;

class InMemoryUserRepositoryTest extends UserRepositoryTestCase
{
    protected function getRepository(): UserRepository
    {
        return new InMemoryUserRepository();
    }
}
