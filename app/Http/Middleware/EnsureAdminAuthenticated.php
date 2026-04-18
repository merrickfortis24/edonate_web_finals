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

        if ($this->supportsTwoFactorStorage()) {
            $admin = DB::table('admins')
                ->select('admin_id', 'two_factor_enabled', 'two_factor_secret')
                ->where('admin_id', (int) $adminId)
                ->first();

            if (!$admin) {
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
                    ->with('error', 'Your account session is no longer valid. Please log in again.');
            }

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

        return $next($request);
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
