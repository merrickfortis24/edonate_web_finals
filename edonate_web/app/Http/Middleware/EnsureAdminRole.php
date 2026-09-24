<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Allow request only when session role matches one of the allowed roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $adminId = $request->session()->get('admin_id');
        if (! is_numeric($adminId) || ! Schema::hasTable('admins')) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'Your account session is no longer valid. Please log in again.');
        }

        $columns = ['role'];
        if (Schema::hasColumn('admins', 'is_active')) {
            $columns[] = 'is_active';
        }
        $admin = DB::table('admins')->select($columns)->where('admin_id', (int) $adminId)->first();
        $sessionRole = strtolower(trim((string) ($admin->role ?? '')));
        if (! $admin || ! in_array($sessionRole, ['admin', 'staff'], true)
            || (isset($admin->is_active) && ! (bool) $admin->is_active)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'Your account session is no longer valid. Please log in again.');
        }

        $request->session()->put('admin_role', $sessionRole);

        $allowedRoles = array_values(array_filter(array_map(
            static fn (string $role): string => strtolower(trim($role)),
            $roles
        )));

        if ($allowedRoles === []) {
            return $next($request);
        }

        if (! in_array($sessionRole, $allowedRoles, true)) {
            return redirect()->route('admin.unauthorized');
        }

        return $next($request);
    }
}
