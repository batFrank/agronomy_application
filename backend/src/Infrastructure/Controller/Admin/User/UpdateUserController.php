<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Admin\User;

use App\Application\Command\UpdateUser;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Infrastructure\Bus\CommandBusAccessor;
use App\Infrastructure\Request\Exception\RequestAttributeExtractorException;
use App\Infrastructure\Request\RequestAttributeExtractor;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Transformer\User\RoleInputTransformer;
use App\Infrastructure\Transformer\User\UserTransformer;
use App\Infrastructure\Validator\User\UpdateUserValidator;
use App\Infrastructure\Validator\Uuid\UuidValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class UpdateUserController extends CommandBusAccessor
{
    /**
     * @throws ValidatorException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws RequestAttributeExtractorException
     */
    public function __invoke(string $id, Request $request): JsonResponse
    {
        UuidValidator::validateUuid($id);

        $payload = json_decode($request->getContent(), true) ?? [];

        UpdateUserValidator::validateUpdateUserRequest($payload);

        $user = $this->execute(
            command: new UpdateUser(
                id: new Uuid($id),
                requestingUser: RequestAttributeExtractor::extractUser($request),
                email: isset($payload['email']) ? new Email($payload['email']) : null,
                password: $payload['password'] ?? null,
                roles: isset($payload['roles']) ? RoleInputTransformer::toUserRoles($payload['roles']) : null,
            ),
        );

        return new JsonResponse(
            ApiResponseContent::createFromData(
                UserTransformer::toArray($user),
            )
                ->toArray(),
            Response::HTTP_OK
        );
    }
}
