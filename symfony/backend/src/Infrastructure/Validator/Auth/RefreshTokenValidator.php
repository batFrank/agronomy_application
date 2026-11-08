<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\Auth;

use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class RefreshTokenValidator extends CommonValidator
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidatorException
     */
    public static function validateRefreshTokenRequest(array $data): void
    {
        $constraints = new Collection([
            'refresh_token' => [
                new NotBlank(message: 'Missing refresh_token parameter'),
                new Type('string'),
            ],
        ]);

        self::validate($data, $constraints);
    }
}
