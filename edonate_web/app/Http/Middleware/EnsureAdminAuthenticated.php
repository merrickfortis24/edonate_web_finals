<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    private const SECURITY_SETTINGS_TABLE = 'admin_security_settings';
    private const SECURITY_DEFAULT_TWO_FACTOR_REQUIRED = true;

    /**
     * Validate that the request has an authenticated admin/staff session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get('admin_id');
        if (! is_numeric($adminId) || ! Schema::hasTable('admins')) {
            return $this->terminateSession($request, 'Please log in to continue.');
        }

        $columns = ['admin_id'];
        foreach (['role', 'username', 'full_name', 'two_factor_enabled', 'two_factor_secret', 'is_active'] as $column) {
            if (Schema::hasColumn('admins', $column)) {
                $columns[] = $column;
            }
        }

        $admin = DB::table('admins')->select($columns)->where('admin_id', (int) $adminId)->first();
        $role = strtolower(trim((string) ($admin->role ?? '')));
        if (! $admin || ! in_array($role, ['admin', 'staff'], true)
            || (isset($admin->is_active) && ! (bool) $admin->is_active)) {
            return $this->terminateSession($request, 'Your account session is no longer valid. Please log in again.');
        }

        $request->session()->put([
            'admin_role' => $role,
            'admin_username' => (string) ($admin->username ?? $request->session()->get('admin_username', '')),
            'admin_full_name' => (string) ($admin->full_name ?? $request->session()->get('admin_full_name', '')),
        ]);

        if ($this->supportsTwoFactorStorage()) {
            $isTwoFactorEnabled = (bool) ($admin->two_factor_enabled ?? false)
                && trim((string) ($admin->two_factor_secret ?? '')) !== '';

            if (
                $this->isGlobalTwoFactorRequired()
                &&
                !$isTwoFactorEnabled
                && !$request->routeIs('admin.2fa.setup', 'admin.2fa.enable', 'admin.logout')
            ) {
                return redirect()
                    ->route('admin.login')
                    ->with('warning', 'Set up Google Authenticator to continue.');
            }
        }

        $now = now()->timestamp;
        $lastActivity = (int) $request->session()->get('admin_last_activity_at', $now);
        $timeoutMinutes = $this->sessionTimeoutMinutes();
        if ($lastActivity > 0 && ($now - $lastActivity) >= ($timeoutMinutes * 60)) {
            return $this->terminateSession($request, 'Session expired due to inactivity. Please log in again.', 401);
        }

        $response = $next($request);
        if ($response->getStatusCode() < 400) {
            $request->session()->put('admin_last_activity_at', $now);
        }

        return $response;
    }

    private function terminateSession(Request $request, string $message, int $status = 302): Response
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'code' => $status === 401 ? 'admin_session_expired' : 'admin_session_invalid',
            ], 401);
        }

        return redirect()->route('admin.login')->with('error', $message);
    }

    private function sessionTimeoutMinutes(): int
    {
        if (! Schema::hasTable(self::SECURITY_SETTINGS_TABLE)
            || ! Schema::hasColumn(self::SECURITY_SETTINGS_TABLE, 'session_timeout_minutes')) {
            return 10;
        }

        $minutes = DB::table(self::SECURITY_SETTINGS_TABLE)
            ->orderByDesc('admin_security_setting_id')
            ->value('session_timeout_minutes');

        return in_array((int) $minutes, [5, 10, 30], true) ? (int) $minutes : 10;
    }

    /**
     * Detect if two-factor columns are present in admins table.
     */
    private function supportsTwoFactorStorage(): bool
    {
        static $supportsTwoFactorStorage;

        if (is_bool($supportsTwoFactorStorage)) {
            return $supportsTwoFactorStorage;
        }

        if (!Schema::hasTable('admins')) {
            $supportsTwoFactorStorage = false;
            return false;
        }

        $supportsTwoFactorStorage = Schema::hasColumn('admins', 'two_factor_enabled')
            && Schema::hasColumn('admins', 'two_factor_secret');

        return $supportsTwoFactorStorage;
    }

    /**
     * Determine if global policy requires 2FA for all admin/staff accounts.
     */
    private function isGlobalTwoFactorRequired(): bool
    {
        if (!$this->supportsGlobalSecuritySettingsStorage()) {
            return self::SECURITY_DEFAULT_TWO_FACTOR_REQUIRED;
        }

        $row = DB::table(self::SECURITY_SETTINGS_TABLE)
            ->select('enforce_two_factor')
            ->orderByDesc('admin_security_setting_id')
            ->first();

        if (!$row) {
            return self::SECURITY_DEFAULT_TWO_FACTOR_REQUIRED;
        }

        return (bool) ($row->enforce_two_factor ?? self::SECURITY_DEFAULT_TWO_FACTOR_REQUIRED);
    }

    /**
     * Detect if global security settings table exists.
     */
    private function supportsGlobalSecuritySettingsStorage(): bool
    {
        static $supportsSecuritySettingsStorage;

        if (is_bool($supportsSecuritySettingsStorage)) {
            return $supportsSecuritySettingsStorage;
        }

        if (!Schema::hasTable(self::SECURITY_SETTINGS_TABLE)) {
            $supportsSecuritySettingsStorage = false;
            return false;
        }

        $supportsSecuritySettingsStorage = Schema::hasColumn(self::SECURITY_SETTINGS_TABLE, 'enforce_two_factor');

        return $supportsSecuritySettingsStorage;
    }
}
