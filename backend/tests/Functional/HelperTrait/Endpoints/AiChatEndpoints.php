<?php

declare(strict_types=1);

namespace App\Tests\Functional\HelperTrait\Endpoints;

class AiChatEndpoints
{
    public static function postAiChatEndpoint(): string
    {
        return '/ai/chat';
    }
}
