<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OptionsController
{
    public function __invoke(): JsonResponse
    {
        // This controller handles OPTIONS requests for CORS preflight
        // The actual CORS headers are set by CorsListener
        return new JsonResponse([], Response::HTTP_OK);
    }
}
