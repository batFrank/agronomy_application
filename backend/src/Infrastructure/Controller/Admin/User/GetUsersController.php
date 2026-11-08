<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Admin\User;

use App\Application\Query\GetUsers;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\User\User;
use App\Infrastructure\Bus\QueryBusAccessor;
use App\Infrastructure\Request\Exception\RequestAttributeExtractorException;
use App\Infrastructure\Request\RequestAttributeExtractor;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Transformer\User\UserCriteriaTransformer;
use App\Infrastructure\Transformer\User\UserTransformer;
use App\Infrastructure\Validator\User\GetUsersValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class GetUsersController extends QueryBusAccessor
{
    public const int DEFAULT_PAGE = 1;
    public const int DEFAULT_LIMIT = 10;

    /**
     * @throws ValidatorException
     * @throws CollectionException
     * @throws RequestAttributeExtractorException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $queryParameters = $request->query->all();

        $queryParameters['page'] = (int) ($queryParameters['page'] ?? self::DEFAULT_PAGE);
        GetUsersValidator::validateGetUsersRequest($queryParameters);

        $queryParameters['limit'] = self::DEFAULT_LIMIT;
        $criteria = UserCriteriaTransformer::fromArray($queryParameters);

        $requestingUser = RequestAttributeExtractor::extractUser($request);

        $users = $this->ask(
            query: new GetUsers(
                criteria: $criteria,
                requestingUser: $requestingUser->getUser(),
                needsCreator: true,
            ),
        );

        return new JsonResponse(
            ApiResponseContent::createFromData(
                array_map(
                    fn (User $user) => UserTransformer::toArray($user),
                    $users->getCollection()->all()
                )
            )
                ->addPaginationMetadata($users, $criteria)
                ->toArray(),
            Response::HTTP_OK
        );
    }
}
