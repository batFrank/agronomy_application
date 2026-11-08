<?php

declare(strict_types=1);

namespace App\Infrastructure\Request;

use App\Application\Security\User;
use App\Infrastructure\Listener\RequestListener;
use App\Infrastructure\Request\Exception\RequestAttributeExtractorException;
use Symfony\Component\HttpFoundation\Request;

class RequestAttributeExtractor
{
    /**
     * @throws RequestAttributeExtractorException
     */
    public static function extractUser(Request $request): User
    {
        $user = $request->attributes->get(RequestListener::LOGGED_USER_ATTRIBUTE);

        if (!$user instanceof User) {
            throw RequestAttributeExtractorException::userNotFound();
        }

        return $user;
    }
}
