<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\AI;

use App\Application\AI\AgentConversation;
use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

/**
 * The shape of what the client sends to the chat: the transport. What a conversation has to be to be
 * valid (it ends with the user, the window, the length of each turn) is checked by AgentConversation.
 */
class AiChatValidator extends CommonValidator
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidatorException
     */
    public static function validateAiChatRequest(array $data): void
    {
        $constraints = new Collection([
            // Symfony's constraints skip a null: each level asks for it not to be one.
            'turns' => new Sequentially([
                new NotNull(),
                new Type('array'),
                new Count(
                    min: 1,
                    max: AgentConversation::MAX_RECEIVED_TURNS,
                    minMessage: 'The conversation must have at least one turn.',
                    maxMessage: 'The conversation cannot have more than {{ limit }} turns.',
                ),
                new All([
                    new NotNull(),
                    new Collection([
                        'role' => new Sequentially([
                            new NotNull(),
                            new Type('string'),
                            new Choice(choices: ['user', 'assistant']),
                        ]),
                        // The same trim AgentTurn::of() does, so a text of only blanks is a 400 with its path
                        // and not a turn that cannot be built.
                        'content' => new Sequentially([
                            new Type('string'),
                            new NotBlank(normalizer: 'trim'),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        self::validate($data, $constraints);
    }
}
