<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Auth;

use App\Application\Command\CheckPasswordResetToken;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Infrastructure\Bus\CommandBusAccessor;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Validator\User\PostUserPasswordCheckValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class PostUserPasswordCheckController extends CommandBusAccessor
{
    /**
     * @throws ValidatorException
     */
    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        PostUserPasswordCheckValidator::validatePostUserPasswordCheckRequest($payload);

        try {
            $isValid = $this->execute(
                command: new CheckPasswordResetToken(
                    resetToken: $payload['reset_token'],
                ),
            );

            return new JsonResponse(
                ApiResponseContent::createFromData([
                    'valid' => $isValid,
                ])->toArray(),
                Response::HTTP_OK
            );
        } catch (InfrastructureException|UserNotFoundException) {
            return new JsonResponse(
                ApiResponseContent::createFromData([
                    'valid' => false,
                ])->toArray(),
                Response::HTTP_OK
            );
        }
    }
}
