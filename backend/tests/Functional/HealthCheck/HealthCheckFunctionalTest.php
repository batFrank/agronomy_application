<?php

declare(strict_types=1);

namespace App\Tests\Functional\HealthCheck;

use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckFunctionalTest extends FunctionalTestCase
{
    public function testHealthCheckReturnsNoContent(): void
    {
        $response = $this->makeRequest(
            method: 'GET',
            uri: '/health-check',
        );

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testHealthCheckIsPubliclyAccessible(): void
    {
        // Verify that the health check doesn't require authentication
        $response = $this->makeRequest(
            method: 'GET',
            uri: '/health-check',
        );

        // Should not return 401 Unauthorized
        $this->assertNotEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
