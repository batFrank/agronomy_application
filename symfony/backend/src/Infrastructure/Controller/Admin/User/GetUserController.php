<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Admin\User;

use App\Application\Query\GetUser;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\UserCriteria;
use App\Infrastructure\Bus\QueryBusAccessor;
use App\Infrastructure\Request\Exception\RequestAttributeExtractorException;
use App\Infrastructure\Request\RequestAttributeExtractor;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Transformer\User\UserTransformer;
use App\Infrastructure\Validator\Uuid\UuidValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class GetUserController extends QueryBusAccessor
{
    /**
     * @throws ValidatorException
     * @throws InvalidUuidException
     * @throws RequestAttributeExtractorException
     */
    public function __invoke(string $id, Request $request): JsonResponse
    {
        UuidValidator::validateUuid($id);

        $criteria = UserCriteria::createById(new Uuid($id));
        $requestingUser = RequestAttributeExtractor::extractUser($request);

        $user = $this->ask(
            query: new GetUser(
                criteria: $criteria,
                requestingUser: $requestingUser->getUser(),
                needsCreator: true,
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
