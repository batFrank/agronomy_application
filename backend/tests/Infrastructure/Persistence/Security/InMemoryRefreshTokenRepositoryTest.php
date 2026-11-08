<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Security;

use App\Domain\Security\RefreshTokenRepository;
use App\Infrastructure\Persistence\Security\InMemoryRefreshTokenRepository;
use App\Tests\Domain\Security\RefreshTokenRepositoryTestCase;

class InMemoryRefreshTokenRepositoryTest extends RefreshTokenRepositoryTestCase
{
    protected function getRepository(): RefreshTokenRepository
    {
        return new InMemoryRefreshTokenRepository();
    }
}
