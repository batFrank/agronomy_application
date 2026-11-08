<?php

declare(strict_types=1);

namespace App\Domain\Translation\Exception;

use App\Domain\Shared\Exception\NotFoundException;

class TranslationNotFoundException extends NotFoundException
{
    private const string MESSAGE = 'Translation not found';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
