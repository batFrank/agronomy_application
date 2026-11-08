<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Auth;

use App\Application\Command\ResetUserPassword;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Infrastructure\Bus\CommandBusAccessor;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Validator\User\PostUserPasswordResetValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class PostUserPasswordResetController extends CommandBusAccessor
{
    /**
     * @throws ValidatorException
     */
    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);

        try {
            $this->execute(
                command: new ResetUserPassword(
                    resetToken: $payload['reset_token'],
                    newPassword: $payload['new_password'],
                ),
            );

            return new JsonResponse(
                ApiResponseContent::createFromMessage(
                    'Password reset successfully'
                )->toArray(),
                Response::HTTP_OK
            );
        } catch (UserNotFoundException) {
            return new JsonResponse(
                ApiResponseContent::createFromMessage(
                    message: 'Invalid or expired reset token',
                    errorCode: 1,
                )->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        } catch (InfrastructureException) {
            return new JsonResponse(
                ApiResponseContent::createFromMessage(
                    message: 'An error occurred while resetting your password',
                    errorCode: 1,
                )->toArray(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
