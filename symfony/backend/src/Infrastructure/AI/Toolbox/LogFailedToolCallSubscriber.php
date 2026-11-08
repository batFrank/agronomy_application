<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox;

use App\Application\Log\Logger;
use App\Domain\Shared\Exception\ForbiddenException;
use App\Domain\Shared\Exception\LoggableException;
use App\Domain\Shared\Exception\NotFoundException;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Exception\InvalidToolCallArgumentsException;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The criterion is the one of the HTTP ExceptionListener: expected failures are not logged, the
 * LoggableException ones and the rest are.
 *
 * What is guaranteed: the arguments payload is not dumped, only their keys are logged. What is not:
 * the message of the cause may contain values (an InvalidEmailException carries the email), exactly
 * as in the HTTP channel, because that is what makes the log useful to diagnose a failure. So these
 * logs are internal (app.log, Rollbar) and must never be forwarded to the AI provider.
 */
final readonly class LogFailedToolCallSubscriber implements EventSubscriberInterface
{
    private const array EXPECTED = [
        NotFoundException::class,
        ForbiddenException::class,
        ValidatorException::class,
        InvalidToolCallArgumentsException::class,
        ToolException::class,
        AccessDeniedException::class,
    ];

    public function __construct(
        private Logger $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ToolCallFailed::class => 'onToolCallFailed'];
    }

    public function onToolCallFailed(ToolCallFailed $event): void
    {
        $exception = $event->getException();
        // A ToolCallException without previous is a reason thrown on purpose by the tool.
        $cause = $exception instanceof ToolCallException ? $exception->getPrevious() : $exception;
        if (null === $cause || $this->isExpected($cause)) {
            return;
        }

        $toolName = $event->getDefinition()->getName();

        $this->logger->error(
            message: \sprintf('Tool "%s" failed.', $toolName),
            exception: $cause,
            extraData: [
                'tool' => $toolName,
                'arguments' => array_keys($event->getArguments()),
            ],
        );
    }

    private function isExpected(\Throwable $cause): bool
    {
        if ($cause instanceof LoggableException) {
            return false;
        }

        foreach (self::EXPECTED as $expectedClass) {
            if ($cause instanceof $expectedClass) {
                return true;
            }
        }

        return false;
    }
}
