<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Security\ApiKeyAuthenticationServiceInterface;
use App\Domain\Security\FirewallInterface;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Exception\UnauthorizedException;
use App\Infrastructure\Service\RouteMethodChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\User\UserInterface;

class Firewall implements FirewallInterface
{
    public const string PUBLIC_ACCESS = 'PUBLIC_ACCESS';
    public const string IS_AUTHENTICATED_FULLY = 'IS_AUTHENTICATED_FULLY';
    public const string X_API_KEY = 'X_API_KEY';

    private readonly RoleHierarchy $roleHierarchy;

    /**
     * @param array<string, mixed>         $security
     * @param array<string, array<string>> $roleHierarchy
     */
    public function __construct(
        private readonly array $security,
        private readonly RouteMethodChecker $routeChecker,
        private readonly ApiKeyAuthenticationServiceInterface $apiKeyAuthenticationService,
        array $roleHierarchy = [],
    ) {
        // Convert arrays to lists for RoleHierarchy constructor
        $hierarchyAsLists = array_map(fn (array $roles): array => array_values($roles), $roleHierarchy);
        $this->roleHierarchy = new RoleHierarchy($hierarchyAsLists);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAccessControl(): array
    {
        return $this->security['access_control'] ?? [];
    }

    /**
     * @throws InfrastructureException
     * @throws UnauthorizedException
     */
    public function isGranted(Request $request, ?UserInterface $user = null): bool
    {
        if (Request::METHOD_OPTIONS === $request->getMethod()) {
            if ($this->routeChecker->isOptionsOnlyRoute($request->getPathInfo())) {
                throw new RouteNotFoundException(
                    'No route found for "' . $request->getMethod() . ' ' . $request->getPathInfo() . '"'
                );
            }

            return true;
        }

        if (!str_starts_with($request->getPathInfo(), $this->security['prefix'])) {
            return true;
        }

        // Strip the prefix from the path for matching against access control rules
        $pathWithoutPrefix = substr($request->getPathInfo(), \strlen($this->security['prefix']));
        $accessControl = $this->getAccessControl();

        foreach ($accessControl as $control) {
            $path = trim(str_replace('\/', '/', $control['path'] ?? ''));
            if ($this->pathMatches($pathWithoutPrefix, $path) && $this->methodMatches($control, $request)) {
                // Check the first matching rule, no other rules should be checked
                $accessRoles = $control['roles'] ?? null;

                if ($this->checkRoles($accessRoles, self::PUBLIC_ACCESS)) {
                    return true;
                }

                if ($this->isApiKeyOnlyAccess($accessRoles)) {
                    $this->apiKeyAuthenticationService->authenticate($request->headers->get('X-API-KEY'));

                    return true;
                }

                if (!$user) {
                    throw new UnauthorizedHttpException('Unauthorized');
                }

                // Check for authenticated access
                if ($this->checkRoles($accessRoles, self::IS_AUTHENTICATED_FULLY)) {
                    return true;
                }

                // Check user roles
                if ($this->checkRolesInHierarchy($user, $accessRoles)) {
                    return true;
                }

                return false;
            }
        }

        // By default, requires the user to be authenticated and have the ROLE_ADMIN to avoid security breaches
        return null !== $user && $this->checkRoles($user->getRoles(), 'ROLE_ADMIN');
    }

    /**
     * @param array<string> $roles
     */
    private function checkRolesInHierarchy(UserInterface $user, array $roles): bool
    {
        // Get all roles the user has access to
        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());

        // If one of the required roles is available inside $reachableRoles, return true
        foreach ($roles as $role) {
            if (\in_array($role, $reachableRoles, true)) {
                return true;
            }
        }

        return false;
    }

    private function checkRoles(mixed $roles, string $role): bool
    {
        if (\is_array($roles) && \in_array($role, $roles)) {
            return true;
        }

        return $roles === $role;
    }

    private function isApiKeyOnlyAccess(mixed $roles): bool
    {
        if (self::X_API_KEY === $roles) {
            return true;
        }

        return \is_array($roles) && [self::X_API_KEY] === array_values($roles);
    }

    private function pathMatches(string $path, string $regexp): bool
    {
        return (bool) preg_match('{' . $regexp . '}', rawurldecode($path));
    }

    /**
     * Whether this rule applies to the request's method.
     *
     * A rule that declares no `methods`, or an empty one, applies to every method. A rule
     * that does declare them and does not match is skipped, so evaluation continues to the
     * rules below it; it is not a denial. Matching on the path and then denying would make a
     * narrowing rule shadow every broader rule beneath it, which is the opposite of what a
     * method restriction is written to do, and it is not how Symfony's own `access_control`
     * behaves.
     *
     * @param array<string, mixed> $control
     */
    private function methodMatches(array $control, Request $request): bool
    {
        $methods = $control['methods'] ?? [];
        $methods = \is_array($methods) ? $methods : [$methods];

        if ([] === $methods) {
            return true;
        }

        $allowed = array_map(static fn (mixed $method): string => strtoupper((string) $method), $methods);

        return \in_array(strtoupper($request->getMethod()), $allowed, true);
    }
}
