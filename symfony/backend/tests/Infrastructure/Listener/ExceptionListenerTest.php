<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Listener;

use App\Application\Log\Logger;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Criteria\InvalidSortException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\BadGatewayException;
use App\Domain\Shared\Exception\BadRequestException;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Exception\ForbiddenException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Exception\LoggableException;
use App\Domain\Shared\Exception\NotFoundException;
use App\Domain\Shared\Exception\UnauthorizedException;
use App\Domain\Shared\Exception\UnprocessableEntityException;
use App\Domain\Shared\Locale\InvalidLocaleException;
use App\Domain\Shared\Media\InvalidFileSizeException;
use App\Domain\Shared\Media\InvalidImageDimensionsException;
use App\Domain\Shared\Media\InvalidMediaConstraintsException;
use App\Domain\Shared\Media\InvalidMimeTypeException;
use App\Domain\Shared\Url\InvalidUrlException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Infrastructure\Listener\ExceptionListener;
use App\Infrastructure\Log\MonologLogger;
use App\Infrastructure\Validator\ValidatorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ExceptionListenerTest extends TestCase
{
    public function testGetSubscribedEventsReturnsKernelExceptionEvent(): void
    {
        $this->assertEquals(
            [
                KernelEvents::EXCEPTION => ['onKernelException', 100],
            ],
            ExceptionListener::getSubscribedEvents(),
        );
    }

    public function testLoggableCodesContainsExpectedCodes(): void
    {
        $this->assertEquals(
            [
                Response::HTTP_INTERNAL_SERVER_ERROR,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            ExceptionListener::LOGGABLE_CODES,
        );
    }

    /**
     * @throws Exception
     */
    #[DataProvider('exceptionResponseCodeProvider')]
    public function testOnKernelExceptionReturnsCorrectResponseCode(
        \Throwable $exception,
        int $expectedStatusCode,
    ): void {
        $logger = $this->createStub(Logger::class);
        $listener = new ExceptionListener($logger);

        $event = $this->createExceptionEvent($exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
    }

    public static function exceptionResponseCodeProvider(): \Generator
    {
        yield 'NotFoundException returns 404' => [
            new NotFoundException('Resource not found'),
            Response::HTTP_NOT_FOUND,
        ];

        yield 'UnauthorizedException returns 401' => [
            new UnauthorizedException('Unauthorized'),
            Response::HTTP_UNAUTHORIZED,
        ];

        yield 'ForbiddenException returns 403' => [
            new ForbiddenException('Forbidden'),
            Response::HTTP_FORBIDDEN,
        ];

        yield 'BadRequestException returns 400' => [
            new BadRequestException('Malformed request.'),
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'BadGatewayException returns 502' => [
            new BadGatewayException('The dependency failed.'),
            Response::HTTP_BAD_GATEWAY,
        ];

        yield 'a denied permission of the user domain returns 403' => [
            UserForbiddenException::cannotUpdateUser(),
            Response::HTTP_FORBIDDEN,
        ];

        yield 'UnprocessableEntityException returns 422' => [
            new UnprocessableEntityException('Cannot process'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'UnauthorizedHttpException returns 401' => [
            new UnauthorizedHttpException('Bearer', 'Invalid token'),
            Response::HTTP_UNAUTHORIZED,
        ];

        yield 'RouteNotFoundException returns 404' => [
            new RouteNotFoundException('Route not found'),
            Response::HTTP_NOT_FOUND,
        ];

        yield 'NotFoundHttpException returns 404' => [
            new NotFoundHttpException('Page not found'),
            Response::HTTP_NOT_FOUND,
        ];

        yield 'BadRequestHttpException returns 400' => [
            new BadRequestHttpException('Bad request'),
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'AccessDeniedException returns 403' => [
            new AccessDeniedException('Access denied'),
            Response::HTTP_FORBIDDEN,
        ];

        yield 'InvalidSortException returns 400' => [
            InvalidSortException::invalidField('invalid_field'),
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'ValidatorException returns 400' => [
            ValidatorException::createByMessage('Validation failed'),
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'DomainException returns 422' => [
            new DomainException('Domain error'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'InvalidEmailException returns 422' => [
            new InvalidEmailException('invalid-email'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'InvalidUuidException returns 422' => [
            new InvalidUuidException('invalid-uuid'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'InvalidUrlException returns 422' => [
            new InvalidUrlException('invalid-url'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'InvalidLocaleException returns 422' => [
            InvalidLocaleException::fromString('invalid-locale'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'InvalidFileSizeException returns 422' => [
            InvalidFileSizeException::fromSize(200, 100),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'InvalidMimeTypeException returns 422' => [
            InvalidMimeTypeException::fromMimeType('text/plain', ['image/png']),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'InvalidImageDimensionsException returns 422' => [
            InvalidImageDimensionsException::fromDimensions(10, 10, 20, 20, 50, 50),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'InvalidMediaConstraintsException returns 422' => [
            InvalidMediaConstraintsException::maxSizeMustBePositive(-1),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        yield 'InfrastructureException returns 500' => [
            new InfrastructureException('Infrastructure error'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'Generic Exception returns 500' => [
            new \Exception('Something went wrong'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'SecurityException (internal integrity failure) returns 500' => [
            SecurityException::invalidUserIdentifier(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'RuntimeException returns 500' => [
            new \RuntimeException('Runtime error'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];
    }

    /**
     * @throws Exception
     */
    public function testOnKernelExceptionReturnsJsonResponseWithMessage(): void
    {
        $logger = $this->createStub(Logger::class);
        $listener = new ExceptionListener($logger);

        $exceptionMessage = 'Test error message';
        $exception = new \Exception($exceptionMessage);
        $event = $this->createExceptionEvent($exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);

        $content = json_decode((string) $response->getContent(), true);
        $this->assertEquals($exceptionMessage, $content['message']);
        $this->assertEquals(1, $content['error']);
    }

    /**
     * @throws Exception
     */
    public function testOnKernelExceptionIncludesValidatorViolationsInMetadata(): void
    {
        $logger = $this->createStub(Logger::class);
        $listener = new ExceptionListener($logger);

        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'This value should not be blank.',
                null,
                [],
                '',
                'email',
                '',
            ),
            new ConstraintViolation(
                'This value is too short.',
                null,
                [],
                '',
                'name',
                '',
            ),
        ]);

        $exception = ValidatorException::createByViolations($violations);
        $event = $this->createExceptionEvent($exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);

        $content = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('metadata', $content);
        $this->assertArrayHasKey('violations', $content['metadata']);
        $this->assertEquals([
            'email' => 'This value should not be blank.',
            'name' => 'This value is too short.',
        ], $content['metadata']['violations']);
    }

    /**
     * @throws Exception
     */
    public function testOnKernelExceptionHandlesValidatorExceptionWithoutViolations(): void
    {
        $logger = $this->createStub(Logger::class);
        $listener = new ExceptionListener($logger);

        $exception = ValidatorException::createByMessage('Validation failed');
        $event = $this->createExceptionEvent($exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Validation failed', $content['message']);
        $this->assertArrayHasKey('metadata', $content);
        $this->assertEquals(['violations' => []], $content['metadata']);
    }

    /**
     * @throws Exception
     */
    #[DataProvider('valueObjectExceptionProvider')]
    public function testOnKernelExceptionReturnsUnprocessableEntityWithMessageForValueObjectExceptions(
        \Throwable $exception,
    ): void {
        $logger = $this->createStub(Logger::class);
        $listener = new ExceptionListener($logger);

        $event = $this->createExceptionEvent($exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        $this->assertEquals($exception->getMessage(), $content['message']);
        $this->assertEquals(1, $content['error']);
    }

    public static function valueObjectExceptionProvider(): \Generator
    {
        yield 'InvalidEmailException' => [
            new InvalidEmailException('invalid@'),
        ];

        yield 'InvalidUuidException' => [
            new InvalidUuidException('bad-uuid'),
        ];

        yield 'InvalidUrlException' => [
            new InvalidUrlException('bad-url'),
        ];

        yield 'InvalidLocaleException' => [
            InvalidLocaleException::fromString('bad-locale'),
        ];

        yield 'InvalidFileSizeException' => [
            InvalidFileSizeException::fromSize(2000, 1000),
        ];

        yield 'InvalidMimeTypeException' => [
            InvalidMimeTypeException::fromMimeType('application/x-tar', ['application/pdf']),
        ];

        yield 'InvalidImageDimensionsException' => [
            InvalidImageDimensionsException::fromDimensions(5, 5, 10, 10, 50, 50),
        ];

        yield 'InvalidMediaConstraintsException' => [
            InvalidMediaConstraintsException::maxSizeMustBePositive(-5),
        ];
    }

    /**
     * @throws Exception
     */
    #[DataProvider('loggableExceptionProvider')]
    public function testOnKernelExceptionLogsException(\Throwable $exception): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo($exception->getMessage()),
                $this->identicalTo($exception),
                $this->callback(fn ($extraData): bool => \is_array($extraData)),
            );

        $listener = new ExceptionListener($logger);
        $event = $this->createExceptionEvent($exception);

        $listener->onKernelException($event);
    }

    /**
     * The body of the request is what the person typed: it must not reach the log, which can end up in
     * a third party service. A sentinel in the body of the request must not show up in it, whatever
     * the exception is.
     *
     * @throws Exception
     */
    #[DataProvider('loggableExceptionProvider')]
    public function testTheBodyOfTheRequestNeverReachesTheLog(\Throwable $exception): void
    {
        // Arrange
        $sentinel = 'sentinel-secret-' . bin2hex(random_bytes(6));
        $received = null;
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
            ->method('error')
            ->willReturnCallback(function (string $message, ?\Throwable $exception, array $extraData) use (&$received): void {
                $received = $extraData;
            });
        $request = new Request(content: json_encode(['turns' => [['role' => 'user', 'content' => $sentinel]]], \JSON_THROW_ON_ERROR));

        // Act
        (new ExceptionListener($logger))->onKernelException($this->createExceptionEvent($exception, $request));

        // Assert
        self::assertIsArray($received);
        self::assertArrayNotHasKey('content', $received);
        self::assertStringNotContainsString($sentinel, json_encode($received, \JSON_THROW_ON_ERROR));
    }

    /**
     * The same, against the real logger and what it writes, and not only against what the listener
     * hands over.
     */
    public function testTheBodyOfTheRequestIsNotInTheRealLogFile(): void
    {
        // Arrange
        $sentinel = 'sentinel-secret-' . bin2hex(random_bytes(6));
        $directory = sys_get_temp_dir() . '/listener-log-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $logger = new MonologLogger($directory);
        $request = new Request(content: json_encode(['turns' => [['role' => 'user', 'content' => $sentinel]]], \JSON_THROW_ON_ERROR));

        // Act
        (new ExceptionListener($logger))->onKernelException($this->createExceptionEvent(new \RuntimeException('Something failed.'), $request));

        // Assert
        try {
            $log = implode("\n", $logger->getLogMessages());
            self::assertStringContainsString('Something failed.', $log, 'The error itself must still be logged.');
            self::assertStringNotContainsString($sentinel, $log);
        } finally {
            array_map('unlink', glob($directory . '/*') ?: []);
            rmdir($directory);
        }
    }

    public static function loggableExceptionProvider(): \Generator
    {
        yield 'Internal server error (500) is logged' => [
            new \Exception('Internal server error'),
        ];

        yield 'UnprocessableEntityException (422) is logged' => [
            new UnprocessableEntityException('Cannot process entity'),
        ];

        yield 'DomainException (422) is logged' => [
            new DomainException('Domain error'),
        ];

        yield 'InfrastructureException (500) is logged' => [
            new InfrastructureException('Infrastructure error'),
        ];

        yield 'BadGatewayException (502) is logged' => [
            new BadGatewayException('The dependency failed.'),
        ];

        yield 'LoggableException interface is logged regardless of code' => [
            new class('Loggable error') extends NotFoundException implements LoggableException {},
        ];
    }

    /**
     * @throws Exception
     */
    #[DataProvider('nonLoggableExceptionProvider')]
    public function testOnKernelExceptionDoesNotLogException(\Throwable $exception): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())
            ->method('error');

        $listener = new ExceptionListener($logger);
        $event = $this->createExceptionEvent($exception);

        $listener->onKernelException($event);
    }

    public static function nonLoggableExceptionProvider(): \Generator
    {
        yield 'NotFoundException (404) is not logged' => [
            new NotFoundException('Not found'),
        ];

        yield 'UnauthorizedException (401) is not logged' => [
            new UnauthorizedException('Unauthorized'),
        ];

        yield 'ForbiddenException (403) is not logged' => [
            new ForbiddenException('Forbidden'),
        ];

        yield 'BadRequestHttpException (400) is not logged' => [
            new BadRequestHttpException('Bad request'),
        ];

        yield 'BadRequestException (400) is not logged' => [
            new BadRequestException('Malformed request.'),
        ];

        yield 'ValidatorException (400) is not logged' => [
            ValidatorException::createByMessage('Validation failed'),
        ];
    }

    private function createExceptionEvent(\Throwable $exception, ?Request $request = null): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request ??= new Request();

        return new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
