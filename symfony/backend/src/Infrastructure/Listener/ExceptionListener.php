<?php

declare(strict_types=1);

namespace App\Infrastructure\Listener;

use App\Application\Log\Logger;
use App\Domain\Shared\Criteria\InvalidSortException;
use App\Domain\Shared\Exception\BadGatewayException;
use App\Domain\Shared\Exception\BadRequestException;
use App\Domain\Shared\Exception\ForbiddenException;
use App\Domain\Shared\Exception\LoggableException;
use App\Domain\Shared\Exception\NotFoundException;
use App\Domain\Shared\Exception\UnauthorizedException;
use App\Domain\Shared\Exception\UnprocessableEntityException;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ExceptionListener implements EventSubscriberInterface
{
    public const array LOGGABLE_CODES = [
        Response::HTTP_INTERNAL_SERVER_ERROR,
        Response::HTTP_UNPROCESSABLE_ENTITY,
    ];

    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 100],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Main classes
        $responseCode = $this->getResponseCode($exception);
        $message = $exception->getMessage();

        $responseContent = ApiResponseContent::createFromMessage(
            message: $message,
            errorCode: 1,
        );

        if ($exception instanceof ValidatorException) {
            $violations = [];
            $violationList = $exception->getViolations();
            if (null !== $violationList) {
                foreach ($violationList as $violation) {
                    $violations[$violation->getPropertyPath()] = $violation->getMessage();
                }
            }
            $responseContent = $responseContent->setMetadata(['violations' => $violations]);
        }

        if (\in_array($responseCode, self::LOGGABLE_CODES, true) || $exception instanceof LoggableException) {
            $this->logger->error(
                message: $message,
                exception: $exception,
                // The body of the request is not logged: it can carry what the person typed (a whole
                // conversation, for the chat), and the log can end up in a third party service.
                extraData: [
                    'query_string' => $event->getRequest()->getQueryString(),
                ],
            );
        }

        $event->setResponse(
            new JsonResponse(
                $responseContent->toArray(),
                $responseCode
            )
        );
    }

    private function getResponseCode(\Throwable $exception): int
    {
        if ($exception instanceof NotFoundException) {
            return Response::HTTP_NOT_FOUND;
        }

        if ($exception instanceof UnauthorizedException) {
            return Response::HTTP_UNAUTHORIZED;
        }

        if ($exception instanceof ForbiddenException) {
            return Response::HTTP_FORBIDDEN;
        }

        if ($exception instanceof BadRequestException) {
            return Response::HTTP_BAD_REQUEST;
        }

        if ($exception instanceof UnprocessableEntityException) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        if ($exception instanceof BadGatewayException) {
            return Response::HTTP_BAD_GATEWAY;
        }

        return match (\get_class($exception)) {
            UnauthorizedHttpException::class => Response::HTTP_UNAUTHORIZED,
            RouteNotFoundException::class,
            NotFoundHttpException::class => Response::HTTP_NOT_FOUND,
            BadRequestHttpException::class,
            InvalidSortException::class,
            ValidatorException::class => Response::HTTP_BAD_REQUEST,
            AccessDeniedException::class,
            HttpExceptionInterface::class => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
