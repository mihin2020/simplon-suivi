<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Guards against a route being added with authentication only and no authorization.
 *
 * A route passes when it has a "permission:" middleware, or when its controller method calls
 * authorize()/can()/a policy, or when its Form Request implements a real authorize().
 */
class RouteAuthorizationTest extends TestCase
{
    /** Routes that are intentionally reachable by any authenticated user (they only expose the user's own data). */
    private const ANY_AUTHENTICATED_USER = [
        'GET /',
        'POST deconnexion',
        'GET notifications',
        'POST notifications/mark-all-read',
        'PATCH notifications/{notification}/read',
        'GET notifications/recent',
        'GET notifications/unread-count',
        'GET presences',
        'GET profil',
        'PUT profil/informations',
        'PUT profil/mot-de-passe',
        'GET chatbot/status',
    ];

    /**
     * Known gaps found by the audit, to be fixed: no new entry may be added here.
     * Remove each line once the route is protected.
     */
    private const KNOWN_GAPS = [
    ];

    public function test_every_authenticated_route_checks_authorization(): void
    {
        $unprotected = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if (! $this->requiresAuthentication($route) || $this->hasPermissionMiddleware($route) || $this->controllerChecksAuthorization($route)) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $unprotected[] = $method.' '.$route->uri();
            }
        }

        $allowed = [...self::ANY_AUTHENTICATED_USER, ...self::KNOWN_GAPS];

        $this->assertSame(
            [],
            array_values(array_diff($unprotected, $allowed)),
            'These routes are authenticated but never check authorization. Add a permission middleware, authorize() or a Form Request authorize().',
        );
    }

    public function test_known_gaps_list_only_contains_routes_that_are_still_unprotected(): void
    {
        $unprotected = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if ($this->requiresAuthentication($route) && ! $this->hasPermissionMiddleware($route) && ! $this->controllerChecksAuthorization($route)) {
                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    $unprotected[] = $method.' '.$route->uri();
                }
            }
        }

        $this->assertSame([], array_values(array_diff(self::KNOWN_GAPS, $unprotected)), 'Remove fixed routes from KNOWN_GAPS.');
    }

    private function requiresAuthentication(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:'))) {
                return true;
            }
        }

        return false;
    }

    private function hasPermissionMiddleware(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                return true;
            }
        }

        return false;
    }

    private function controllerChecksAuthorization(Route $route): bool
    {
        $action = $route->getActionName();

        if ($action === 'Closure') {
            return $this->sourceChecksAuthorization(new ReflectionFunction($route->getAction('uses')));
        }

        [$class, $method] = array_pad(explode('@', $action), 2, '__invoke');
        $reflection = new ReflectionMethod($class, $method);

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), FormRequest::class)) {
                $authorize = new ReflectionMethod($type->getName(), 'authorize');

                if (! preg_match('/return\s+true\s*;/', $this->body($authorize))) {
                    return true;
                }
            }
        }

        return $this->sourceChecksAuthorization($reflection);
    }

    private function sourceChecksAuthorization(ReflectionFunction|ReflectionMethod $reflection): bool
    {
        return (bool) preg_match(
            '/->authorize\(|authorizeForUser|->can\(|->cannot\(|Gate::|hasPermission\(|isSuperAdmin\(/',
            $this->body($reflection),
        );
    }

    private function body(ReflectionFunction|ReflectionMethod $reflection): string
    {
        $lines = file($reflection->getFileName());

        return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }
}
