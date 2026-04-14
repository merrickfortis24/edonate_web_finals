<?php

namespace App\Http\Controllers;

use App\Mail\AdminPasswordResetMail;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminAuthController extends Controller
{
    private const REMEMBER_COOKIE_NAME = 'admin_remember';
    private const REMEMBER_DAYS = 30;
    private const RESET_TOKEN_TTL_MINUTES = 30;
    private const RESET_CACHE_PREFIX = 'admin_password_reset:';
    private const RESET_CACHE_STORE = 'file';

    /**
     * Display admin login page.
     */
    public function create(Request $request)
    {
        if ($this->hasActiveAdminSession($request)) {
            return redirect()->route(
                $this->dashboardRouteForRole((string) $request->session()->get('admin_role', ''))
            );
        }

        return view('admin.admin_login');
    }

    /**
     * Handle admin login and redirect to dashboard.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $admin = DB::table('admins')
            ->where('email', $validated['email'])
            ->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        $role = $this->normalizeRole((string) ($admin->role ?? ''));
        if (!$this->isSupportedRole($role)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Your account role is not authorized to access this portal.']);
        }

        $this->setAdminSession($request, $admin, $role);

        if ($request->boolean('remember')) {
            $this->issueRememberMeToken((int) $admin->admin_id);
        } else {
            $this->clearRememberMeToken((int) $admin->admin_id);
        }

        return redirect()->route($this->dashboardRouteForRole($role));
    }

    /**
     * Display forgot password module for admin accounts.
     */
    public function forgotPassword(Request $request)
    {
        if ($this->hasActiveAdminSession($request)) {
            return redirect()->route(
                $this->dashboardRouteForRole((string) $request->session()->get('admin_role', ''))
            );
        }

        return view('admin.forgot_pass');
    }

    /**
     * Handle admin forgot password request.
     */
    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        $admin = DB::table('admins')
            ->select('admin_id', 'email', 'full_name', 'username')
            ->where('email', $validated['email'])
            ->first();

        if ($admin) {
            $token = Str::random(64);
            $resetPath = route('admin.password.reset.form', [
                'email' => $admin->email,
                'token' => $token,
            ], false);
            $resetUrl = rtrim($request->getSchemeAndHttpHost(), '/').$resetPath;

            Cache::store(self::RESET_CACHE_STORE)->put(
                $this->makePasswordResetCacheKey($admin->email),
                ['token_hash' => Hash::make($token)],
                now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES)
            );

            try {
                Mail::to($admin->email)->send(new AdminPasswordResetMail(
                    (string) ($admin->full_name ?: $admin->username ?: 'Admin'),
                    $resetUrl,
                    self::RESET_TOKEN_TTL_MINUTES
                ));
            } catch (Throwable $exception) {
                logger()->error('Failed to send admin password reset email.', [
                    'email' => $admin->email,
                    'ip' => $request->ip(),
                    'message' => $exception->getMessage(),
                ]);
            }

            logger()->info('Admin password reset requested.', [
                'admin_id' => $admin->admin_id,
                'email' => $admin->email,
                'ip' => $request->ip(),
            ]);
        }

        return back()->with('success', 'If an account exists for that email, a reset link has been sent.');
    }

    /**
     * Display reset password form for valid admin reset links.
     */
    public function showResetPasswordForm(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'token' => ['required', 'string', 'size:64'],
        ]);

        if (!$this->isValidPasswordResetToken($validated['email'], $validated['token'])) {
            return redirect()
                ->route('admin.password.request')
                ->withErrors(['email' => 'This reset link is invalid or has expired.']);
        }

        return view('admin.reset_password', [
            'email' => $validated['email'],
            'token' => $validated['token'],
        ]);
    }

    /**
     * Update admin password using a valid reset token.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        if (!$this->isValidPasswordResetToken($validated['email'], $validated['token'])) {
            return redirect()
                ->route('admin.password.request')
                ->withErrors(['email' => 'This reset link is invalid or has expired.']);
        }

        $updatePayload = [
            'password' => Hash::make($validated['password']),
        ];

        if ($this->supportsRememberMeStorage()) {
            $updatePayload['remember_token'] = null;
            $updatePayload['remember_token_expires_at'] = null;
        }

        $updatedRows = DB::table('admins')
            ->where('email', $validated['email'])
            ->update($updatePayload);

        Cache::store(self::RESET_CACHE_STORE)->forget($this->makePasswordResetCacheKey($validated['email']));

        if ($updatedRows === 0) {
            return redirect()
                ->route('admin.password.request')
                ->withErrors(['email' => 'Unable to reset password for this account.']);
        }

        return redirect()
            ->route('admin.login')
            ->with('success', 'Your password has been reset. You can now log in.');
    }

    /**
     * Display admin dashboard.
     */
    public function dashboard(Request $request)
    {
        return view('admin.admin_dashboard');
    }

    /**
     * Display staff dashboard.
     */
    public function staffDashboard(Request $request)
    {
        return view('admin.staff_dashboard');
    }

    /**
     * Display user management page.
     */
    public function users(Request $request)
    {
        return view('admin.user_management');
    }

    /**
     * Display appointment management page.
     */
    public function appointments(Request $request)
    {
        return view('admin.appointment_management');
    }

    /**
     * Display donation records page.
     */
    public function donationRecords(Request $request)
    {
        return view('admin.donor_records');
    }

    /**
     * Display blood availability mapping page.
     */
    public function bloodAvailabilityMapping(Request $request)
    {
        return view('admin.blood_availability_mapping');
    }

    /**
     * Display notification center page.
     */
    public function notificationCenter(Request $request)
    {
        return view('admin.notification_center');
    }

    /**
     * Display report and analytics page.
     */
    public function reportAnalytics(Request $request)
    {
        return view('admin.report_analytics');
    }

    /**
     * Display audit logs page.
     */
    public function auditLogs(Request $request)
    {
        return view('admin.audit_log');
    }

    /**
     * Display RBAC management page.
     */
    public function rbac(Request $request)
    {
        return view('admin.rbac');
    }

    /**
     * Display settings page.
     */
    public function settings(Request $request)
    {
        return view('admin.settings');
    }

    /**
     * Display unauthorized page for signed-in users.
     */
    public function unauthorized(Request $request)
    {
        return response()->view('admin.unauthorized', [], 403);
    }

    /**
     * Check if admin has active session or valid remember-me token.
     */
    private function hasActiveAdminSession(Request $request): bool
    {
        if ($request->session()->has('admin_id')) {
            $adminId = $request->session()->get('admin_id');
            $role = $this->normalizeRole((string) $request->session()->get('admin_role', ''));

            if (is_numeric($adminId) && $this->isSupportedRole($role)) {
                return true;
            }

            $request->session()->forget(['admin_id', 'admin_username', 'admin_full_name', 'admin_role']);
        }

        return $this->attemptRememberedLogin($request);
    }

    /**
     * Attempt login using remember-me cookie.
     */
    private function attemptRememberedLogin(Request $request): bool
    {
        $rememberCookie = $request->cookie(self::REMEMBER_COOKIE_NAME);

        if (!$rememberCookie || !$this->supportsRememberMeStorage()) {
            return false;
        }

        try {
            $payload = Crypt::decryptString($rememberCookie);
        } catch (DecryptException $exception) {
            $this->clearRememberMeToken();
            return false;
        }

        $parts = explode('|', $payload, 2);
        if (count($parts) !== 2) {
            $this->clearRememberMeToken();
            return false;
        }

        [$adminId, $plainToken] = $parts;
        if (!is_numeric($adminId) || trim($plainToken) === '') {
            $this->clearRememberMeToken();
            return false;
        }

        $admin = DB::table('admins')
            ->where('admin_id', (int) $adminId)
            ->first();

        if (!$admin) {
            $this->clearRememberMeToken();
            return false;
        }

        $role = $this->normalizeRole((string) ($admin->role ?? ''));
        if (!$this->isSupportedRole($role)) {
            $this->clearRememberMeToken((int) $admin->admin_id);
            return false;
        }

        $expiresAt = !empty($admin->remember_token_expires_at)
            ? Carbon::parse($admin->remember_token_expires_at)
            : null;

        $isTokenValid = !empty($admin->remember_token)
            && $expiresAt !== null
            && !$expiresAt->isPast()
            && Hash::check($plainToken, $admin->remember_token);

        if (!$isTokenValid) {
            $this->clearRememberMeToken((int) $admin->admin_id);
            return false;
        }

        $this->setAdminSession($request, $admin, $role);
        $this->issueRememberMeToken((int) $admin->admin_id);

        return true;
    }

    /**
     * Set authenticated admin data in session.
     */
    private function setAdminSession(Request $request, object $admin, ?string $role = null): void
    {
        $normalizedRole = $this->normalizeRole($role ?? (string) ($admin->role ?? ''));

        $request->session()->regenerate();
        $request->session()->put([
            'admin_id' => $admin->admin_id,
            'admin_username' => $admin->username,
            'admin_full_name' => $admin->full_name,
            'admin_role' => $normalizedRole,
        ]);
    }

    /**
     * Resolve the correct post-login dashboard route per role.
     */
    private function dashboardRouteForRole(string $role): string
    {
        return $this->normalizeRole($role) === 'staff'
            ? 'staff.dashboard'
            : 'admin.dashboard';
    }

    /**
     * Normalize role strings before comparison.
     */
    private function normalizeRole(string $role): string
    {
        return Str::lower(trim($role));
    }

    /**
     * Restrict portal access to known admin roles.
     */
    private function isSupportedRole(string $role): bool
    {
        return in_array($this->normalizeRole($role), ['admin', 'staff'], true);
    }

    /**
     * Issue remember-me token and cookie.
     */
    private function issueRememberMeToken(int $adminId): void
    {
        if (!$this->supportsRememberMeStorage()) {
            return;
        }

        $plainToken = Str::random(64);
        $expiresAt = now()->addDays(self::REMEMBER_DAYS);

        DB::table('admins')
            ->where('admin_id', $adminId)
            ->update([
                'remember_token' => Hash::make($plainToken),
                'remember_token_expires_at' => $expiresAt,
            ]);

        $cookiePayload = Crypt::encryptString($adminId.'|'.$plainToken);

        Cookie::queue(cookie(
            self::REMEMBER_COOKIE_NAME,
            $cookiePayload,
            self::REMEMBER_DAYS * 24 * 60,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            config('session.same_site', 'lax')
        ));
    }

    /**
     * Clear remember-me token and cookie.
     */
    private function clearRememberMeToken(?int $adminId = null): void
    {
        if ($adminId !== null && $this->supportsRememberMeStorage()) {
            DB::table('admins')
                ->where('admin_id', $adminId)
                ->update([
                    'remember_token' => null,
                    'remember_token_expires_at' => null,
                ]);
        }

        Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE_NAME));
    }

    /**
     * Detect if remember-me columns exist in admins table.
     */
    private function supportsRememberMeStorage(): bool
    {
        if (!Schema::hasTable('admins')) {
            return false;
        }

        return Schema::hasColumn('admins', 'remember_token')
            && Schema::hasColumn('admins', 'remember_token_expires_at');
    }

    /**
     * Build cache key for admin password reset token state.
     */
    private function makePasswordResetCacheKey(string $email): string
    {
        return self::RESET_CACHE_PREFIX.Str::lower(trim($email));
    }

    /**
     * Validate password reset token against cached hash.
     */
    private function isValidPasswordResetToken(string $email, string $token): bool
    {
        $cacheData = Cache::store(self::RESET_CACHE_STORE)->get($this->makePasswordResetCacheKey($email));

        if (!is_array($cacheData) || empty($cacheData['token_hash'])) {
            return false;
        }

        return Hash::check($token, (string) $cacheData['token_hash']);
    }

    /**
     * Log admin out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get('admin_id');
        if (is_numeric($adminId)) {
            $this->clearRememberMeToken((int) $adminId);
        } else {
            $this->clearRememberMeToken();
        }

        $request->session()->forget(['admin_id', 'admin_username', 'admin_full_name', 'admin_role']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'You have been logged out.');
    }
}
