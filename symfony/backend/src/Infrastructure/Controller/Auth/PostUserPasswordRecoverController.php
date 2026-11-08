<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Auth;

use App\Application\Command\RecoverUserPassword;
use App\Infrastructure\Bus\CommandBusAccessor;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Validator\User\PostUserPasswordRecoverValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class PostUserPasswordRecoverController extends CommandBusAccessor
{
    /**
     * @throws ValidatorException
     */
    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        PostUserPasswordRecoverValidator::validatePostUserPasswordRecoverRequest($payload);

        $this->execute(
            command: new RecoverUserPassword(
                email: $payload['email'],
            ),
        );

        return new JsonResponse(
            ApiResponseContent::createFromMessage(
                'Password recovery email sent successfully'
            )->toArray(),
            Response::HTTP_OK
        );
    }
}
