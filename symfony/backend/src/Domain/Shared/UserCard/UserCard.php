<?php

declare(strict_types=1);

namespace App\Domain\Shared\UserCard;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Uuid\Uuid;

readonly class UserCard
{
    public function __construct(
        private Uuid $id,
        private Email $email,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }
}
