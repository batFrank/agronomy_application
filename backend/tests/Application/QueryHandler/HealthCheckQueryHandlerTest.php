<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Query\HealthCheckQuery;
use App\Application\QueryHandler\HealthCheckQueryHandler;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\UserRepository;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HealthCheckQueryHandlerTest extends TestCase
{
    private MockObject&UserRepository $userRepository;
    private HealthCheckQueryHandler $handler;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->handler = new HealthCheckQueryHandler($this->userRepository);
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleWithUser(): void
    {
        $query = new HealthCheckQuery();

        $this->userRepository
            ->expects($this->once())
            ->method('countBy')
            ->willReturn(1);

        $this->handler->handle($query);
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleWithUserNotFound(): void
    {
        $query = new HealthCheckQuery();

        $this->userRepository
            ->expects($this->once())
            ->method('countBy')
            ->willReturn(0);

        $this->handler->handle($query);
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleWithException(): void
    {
        $query = new HealthCheckQuery();

        $this->userRepository
            ->expects($this->once())
            ->method('countBy')
            ->willThrowException(new \Exception('Database connection failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->handler->handle($query);
    }
}
