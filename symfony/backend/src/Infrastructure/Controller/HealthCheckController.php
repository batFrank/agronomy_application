<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Query\HealthCheckQuery;
use App\Infrastructure\Bus\QueryBusAccessor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

readonly class HealthCheckController extends QueryBusAccessor
{
    public function __invoke(): JsonResponse
    {
        $this->ask(new HealthCheckQuery());

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
