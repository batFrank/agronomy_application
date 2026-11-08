<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Admin\User;

use App\Application\Command\CreateUser;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\UuidGenerator;
use App\Infrastructure\Bus\CommandBusAccessor;
use App\Infrastructure\Request\Exception\RequestAttributeExtractorException;
use App\Infrastructure\Request\RequestAttributeExtractor;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Transformer\User\RoleInputTransformer;
use App\Infrastructure\Transformer\User\UserTransformer;
use App\Infrastructure\Validator\User\CreateUserValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class CreateUserController extends CommandBusAccessor
{
    /**
     * @throws ValidatorException
     * @throws InvalidEmailException
     * @throws RequestAttributeExtractorException
     */
    public function __invoke(Request $request, UuidGenerator $uuidGenerator): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        CreateUserValidator::validateCreateUserRequest($data);

        $loggedUser = RequestAttributeExtractor::extractUser($request);

        $user = $this->execute(
            command: new CreateUser(
                id: $uuidGenerator->generate(),
                email: new Email($data['email']),
                password: $data['password'],
                roles: RoleInputTransformer::toUserRoles($data['roles']),
                createdBy: $loggedUser->getId(),
            ),
        );

        return new JsonResponse(
            ApiResponseContent::createFromData(
                UserTransformer::toArray($user),
            )
                ->toArray(),
            Response::HTTP_CREATED
        );
    }
}
