<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Translation;

use App\Domain\Translation\TranslationRepository;
use App\Infrastructure\Persistence\Translation\InMemoryTranslationRepository;
use App\Tests\Domain\Translation\TranslationRepositoryTestCase;

class InMemoryTranslationRepositoryTest extends TranslationRepositoryTestCase
{
    protected function getRepository(): TranslationRepository
    {
        return new InMemoryTranslationRepository();
    }
}
