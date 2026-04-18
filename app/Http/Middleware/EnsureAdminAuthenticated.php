<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    /**
     * Validate that the request has an authenticated admin/staff session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get('admin_id');
        $role = strtolower((string) $request->session()->get('admin_role', ''));

        if (!is_numeric($adminId) || !in_array($role, ['admin', 'staff'], true)) {
            $request->session()->forget([
                'admin_id',
                'admin_username',
                'admin_full_name',
                'admin_role',
                'pending_admin_2fa',
                'admin_2fa_setup_secret',
                'two_factor_recovery_codes',
            ]);

            return redirect()
                ->route('admin.login')
                ->with('error', 'Please log in to continue.');
        }

        return $next($request);
    }
}
