<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Agent\UserDirectory;

use App\Application\Query\GetUsers;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\User\PaginatedUserCollection;
use App\Domain\User\User;
use App\Domain\User\UserRole;
use App\Infrastructure\AI\Toolbox\AgentTool;
use App\Infrastructure\AI\Toolbox\ToolCallException;
use App\Infrastructure\Transformer\User\UserCriteriaTransformer;
use App\Infrastructure\Transformer\User\UserTransformer;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * Template for a read-only tool. Copying it is not just renaming: every point marked `ADAPT` below
 * changes with the query, because a different query returns a different type, and with it the
 * assertion and the mapping of the output change too. Nothing hides that on purpose.
 *
 *  1. ADAPT 1 - the query and the arguments the tool exposes to the model (and their `#[Schema]`).
 *  2. ADAPT 2 - the type the query returns, asserted right after asking the bus.
 *  3. ADAPT 3 - the mapping of that result to the array the model receives. If the entity changes,
 *     so does the transformer.
 *  4. ADAPT 4 - the access policy. If the entity changes, so does who may read it; see below.
 *
 * What stays the same in any read-only tool:
 *  - it is a thin adapter: it builds a query and asks the bus, so the rules of the handler apply.
 *    GetUsersQueryHandler scopes a manager to the users they created. Its UserForbiddenException
 *    (a manager asking for the users of somebody else) is NOT reachable with the arguments of this
 *    tool, which never builds a `created_by`; a tool that exposes it gets that failure explained to
 *    the model by the toolbox, one that does not never triggers it;
 *  - the actor comes from AgentContext, never from the request. Whoever starts the agent call (a
 *    controller, a Messenger handler, a command) must call AgentContext::enterAs() before and
 *    leave() in a finally block. That boundary is not part of this tool;
 *  - it returns the transformer output as it is: IdentifierStrippingToolbox removes the identifiers
 *    before the model sees them, so there is nothing to strip here.
 *
 * The role check is the tool's own policy, not something missing from the handler. The HTTP
 * endpoint protects itself in the access_control of security.yaml, which is a policy of that
 * transport; the tool is another way in and may demand other conditions. If the AI tools were a paid
 * feature, for instance, the tool would have to check that the person has contracted them: that fits
 * neither in security.yaml nor in the concerns of the endpoint. That the roles the tool accepts and
 * the ones the endpoint accepts are the same today is circumstantial, and nothing ties them
 * together (ADAPT 4). The tool checks the actor of the AgentContext instead of using
 * `#[IsGrantedTool]` because that attribute depends on a Symfony security token, not on HTTP, and
 * here the identity travels only through the AgentContext.
 */
#[AsTool('list_users', 'Lists the users the current person is allowed to see, one page at a time. Can be filtered by a search text (matched against the email) or by role.')]
final readonly class ListUsersTool extends AgentTool
{
    private const int PAGE_SIZE = 10;

    /**
     * @return array{users: list<array<string, mixed>>, total: int, page: int, page_size: int}
     *
     * @throws ToolCallException
     * @throws CollectionException
     */
    public function __invoke(
        // ADAPT 1: the arguments the model can fill in.
        #[Schema(description: 'Text to look for in the email of the users.', minLength: 1)]
        ?string $search = null,
        #[Schema(description: 'Only the users that have this role.', enum: ['ROLE_USER', 'ROLE_MANAGER', 'ROLE_ADMIN'])]
        ?string $role = null,
        #[Schema(description: 'Page number, starting at 1.', minimum: 1)]
        int $page = 1,
    ): array {
        $actor = $this->context->actor();

        // ADAPT 4: who may call this tool. The policy of the tool, independent from the HTTP endpoint's.
        if (!$actor->hasRole(UserRole::Admin) && !$actor->hasRole(UserRole::Manager)) {
            throw ToolCallException::withReason('The current user is not allowed to list users.');
        }

        $requestingUser = $actor->getUser();
        if (!$requestingUser instanceof User) {
            // Without the domain user the handler cannot scope a manager: fail instead of listing everybody.
            throw new \LogicException('The actor of the agent context has no domain user.');
        }

        // ADAPT 1: the query.
        $users = $this->ask(new GetUsers(
            criteria: UserCriteriaTransformer::fromArray(array_filter([
                'search' => $search,
                'role' => $role,
                'page' => $page,
                'limit' => self::PAGE_SIZE,
            ], static fn (mixed $value): bool => null !== $value)),
            requestingUser: $requestingUser,
            needsCreator: true,
        ));
        // ADAPT 2: what this query returns.
        \assert($users instanceof PaginatedUserCollection);

        // ADAPT 3: the output the model receives.
        return [
            'users' => array_values(array_map(
                static fn (User $user): array => UserTransformer::toArray($user),
                $users->getCollection()->all(),
            )),
            'total' => $users->getTotalItems(),
            'page' => $page,
            'page_size' => self::PAGE_SIZE,
        ];
    }
}
