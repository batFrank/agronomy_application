<?php

declare(strict_types=1);

namespace App\Domain\Translation;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Translation\Exception\TranslationNotFoundException;

interface TranslationRepository
{
    /**
     * @throws InfrastructureException
     */
    public function put(Translation $translation): void;

    /**
     * @throws InfrastructureException
     * @throws TranslationNotFoundException
     */
    public function findOneBy(TranslationCriteria $criteria): Translation;

    /**
     * @throws InfrastructureException
     */
    public function findBy(TranslationCriteria $criteria): TranslationCollection;

    /**
     * @throws InfrastructureException
     * @throws TranslationNotFoundException
     */
    public function delete(Translation $translation): void;
}
