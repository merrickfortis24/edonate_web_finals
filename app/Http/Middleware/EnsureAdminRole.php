<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Allow request only when session role matches one of the allowed roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $sessionRole = strtolower((string) $request->session()->get('admin_role', ''));

        $allowedRoles = array_values(array_filter(array_map(
            static fn (string $role): string => strtolower(trim($role)),
            $roles
        )));

        if ($allowedRoles === []) {
            return $next($request);
        }

        if (!in_array($sessionRole, $allowedRoles, true)) {
            return redirect()->route('admin.unauthorized');
        }

        return $next($request);
    }
}
