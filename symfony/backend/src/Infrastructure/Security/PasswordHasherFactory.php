<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class PasswordHasherFactory
{
    public function __invoke(
        PasswordHasherFactoryInterface $passwordHasherFactory,
        string $name,
    ): PasswordHasherInterface {
        return $passwordHasherFactory->getPasswordHasher($name);
    }
}
