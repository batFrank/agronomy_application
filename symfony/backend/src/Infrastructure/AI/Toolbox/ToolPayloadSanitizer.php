<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox;

/**
 * Removes internal identifiers from a tool payload before the model receives it.
 *
 * Guarantees:
 * - Keys named `id`, `uuid`, or ending in `_id` / `_uuid` (snake_case, case-sensitive) are removed at any depth.
 *   The underscore separator is mandatory, so keys such as `valid`, `paid` or `identity` are kept.
 * - String values that are exactly a UUID are removed whatever their key is (e.g. `created_by`).
 *   A string that only contains a UUID inside a longer text is kept whole.
 * - The same rule applies to a keyed list: a non-empty list whose elements are all exactly a UUID
 *   (e.g. `user_ids`) is removed whole. A mixed list and an empty list are kept.
 * - Nested arrays are traversed recursively. An array left empty is kept as an empty array.
 *
 * Limits:
 * - Only arrays are traversed. Objects, null and scalars are returned untouched, so an object
 *   with a public `id` still reaches the model. The project convention is that transformers return arrays.
 * - Numeric keys and list elements are never removed nor reindexed. A UUID element of a list that has no key
 *   of its own (the root payload, or a list inside another list) is kept: there is no key to remove it by.
 * - The sanitizer is applied automatically by IdentifierStrippingToolbox, which only covers array results.
 */
final class ToolPayloadSanitizer
{
    private const string ID_KEY_PATTERN = '/^(?:id|uuid)$|_(?:id|uuid)$/D';

    private const string UUID_VALUE_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    public static function withoutIds(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (\is_string($key) && (self::isIdKey($key) || self::isUuid($value) || self::isUuidList($value))) {
                continue;
            }

            $clean[$key] = \is_array($value) ? self::withoutIds($value) : $value;
        }

        return $clean;
    }

    private static function isIdKey(string $key): bool
    {
        return 1 === preg_match(self::ID_KEY_PATTERN, $key);
    }

    private static function isUuidList(mixed $value): bool
    {
        if (!\is_array($value) || [] === $value || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $element) {
            if (!self::isUuid($element)) {
                return false;
            }
        }

        return true;
    }

    private static function isUuid(mixed $value): bool
    {
        return \is_string($value) && 1 === preg_match(self::UUID_VALUE_PATTERN, $value);
    }
}
