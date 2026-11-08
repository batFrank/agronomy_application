<?php

declare(strict_types=1);

namespace App\Domain\Bus;

use App\Domain\Bus\Exception\MessageBusException;

interface MessageBus
{
    /**
     * @throws MessageBusException
     */
    public function dispatch(AsyncMessage $message): void;
}
