<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox\Translator;

/**
 * The first translator that supports an exception wins, and the order is the registration order
 * of the tagged services (file order with the resource loader). A translator for a subclass of an
 * exception already covered by another one needs precedence: tag it with
 * #[AsTaggedItem(priority: N)], otherwise the broader translator may silently win.
 *
 * What translate() returns reaches the context of an external provider. A translator that forwards
 * getMessage() exposes the message of the domain exception, so that message must not carry personal
 * data or secrets.
 */
interface ToolExceptionTranslator
{
    public function supports(\Throwable $exception): bool;

    /** Actionable message, written for the agent to explain it to the person. */
    public function translate(\Throwable $exception): string;
}
