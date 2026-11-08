<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Translation;

use App\Domain\Shared\Entity\EntityWithIdCollection;
use App\Domain\Shared\Translation\TranslatableCollection;

/**
 * @extends EntityWithIdCollection<TranslatableMock>
 */
class TranslatableMockCollection extends EntityWithIdCollection implements TranslatableCollection
{
    public function getTypeName(): string
    {
        return TranslatableMock::class;
    }
}
