<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

readonly class RouteMethodChecker
{
    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public function isOptionsOnlyRoute(string $path): bool
    {
        $routes = $this->router->getRouteCollection();

        foreach ($routes as $route) {
            if ($this->routeMatched($route, $path)) {
                $methods = $route->getMethods();
                if (\count($methods) > 1 || (1 === \count($methods) && 'OPTIONS' !== $methods[0])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function getRouteMethods(string $path): array
    {
        $routes = $this->router->getRouteCollection();
        $methods = [];
        foreach ($routes as $route) {
            if ($this->routeMatched($route, $path)) {
                $methods = array_merge($methods, $route->getMethods());
            }
        }

        return array_unique($methods);
    }

    private function routeMatched(Route $route, string $path): bool
    {
        $routePath = $route->getPath();
        $routePattern = '@^' . preg_replace('/\{[^}]+}/', '[^/]+', $routePath) . '$@';

        return 1 === preg_match($routePattern, $path);
    }
}
