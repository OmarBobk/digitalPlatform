<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackendAccess
{
    /**
     * Allow request if the user has at least one backend permission.
     * Otherwise redirect to 404 (same behavior as previous admin-only guard).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(404);
        }

        $backendPermissions = config('permission.backend_permissions', []);

        if (empty($backendPermissions) || ! $user->hasAnyPermission(...$backendPermissions)) {
            abort(404);
        }

        return $next($request);
    }
}
