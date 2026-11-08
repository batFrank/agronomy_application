<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox;

use App\Domain\Bus\Command;
use App\Domain\Bus\CommandBus;
use App\Domain\Bus\Query;
use App\Domain\Bus\QueryBus;
use App\Infrastructure\AI\Context\AgentContext;
use App\Infrastructure\Bus\DuplexBusAccessor;

/**
 * Failures outside the bus (no actor in the context, AccessDeniedException, missing arguments)
 * are not translated here: the package's generic message covers them.
 */
abstract readonly class AgentTool extends DuplexBusAccessor
{
    public function __construct(
        QueryBus $queryBus,
        CommandBus $commandBus,
        protected AgentContext $context,
        private ToolExceptionMapper $exceptionMapper,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

    /** @throws ToolCallException */
    protected function execute(Command $command): mixed
    {
        try {
            return parent::execute($command);
        } catch (\Throwable $exception) {
            throw $this->exceptionMapper->toToolCallException($exception);
        }
    }

    /** @throws ToolCallException */
    protected function ask(Query $query): mixed
    {
        try {
            return parent::ask($query);
        } catch (\Throwable $exception) {
            throw $this->exceptionMapper->toToolCallException($exception);
        }
    }
}
