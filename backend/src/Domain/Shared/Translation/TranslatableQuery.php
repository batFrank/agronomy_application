<?php

declare(strict_types=1);

namespace App\Domain\Shared\Translation;

use App\Domain\Shared\Locale\Locale;

interface TranslatableQuery
{
    public function getLocale(): Locale;
}
