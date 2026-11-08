<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\User;

use App\Application\Query\GetUser;
use App\Domain\User\UserCriteria;
use App\Infrastructure\Bus\QueryBusAccessor;
use App\Infrastructure\Request\Exception\RequestAttributeExtractorException;
use App\Infrastructure\Request\RequestAttributeExtractor;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Transformer\User\UserTransformer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class GetMeController extends QueryBusAccessor
{
    /**
     * @throws RequestAttributeExtractorException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $loggedUser = RequestAttributeExtractor::extractUser($request);

        $user = $this->ask(
            query: new GetUser(
                criteria: UserCriteria::createById($loggedUser->getId()),
                requestingUser: $loggedUser->getUser(),
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
