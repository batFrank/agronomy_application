<?php

declare(strict_types=1);

namespace App\Domain\Shared\Uuid;

interface UuidGenerator
{
    public function generate(): Uuid;
}
