<?php

declare(strict_types=1);

namespace App\Domain\Translation;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Locale\Locale;
use App\Domain\Shared\Translation\Translatable;
use App\Domain\Shared\Translation\TranslatableCollection;

interface TranslationServiceInterface
{
    /**
     * @throws InfrastructureException
     */
    public function translate(Translatable $translatable, Locale $locale): void;

    /**
     * @throws InfrastructureException
     */
    public function translateCollection(TranslatableCollection $collection, Locale $locale): void;
}
