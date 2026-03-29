<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    private const REMEMBER_COOKIE_NAME = 'admin_remember';
    private const REMEMBER_DAYS = 30;

    /**
     * Display admin login page.
     */
    public function create(Request $request)
    {
        if ($this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.dashboard');
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

        $this->setAdminSession($request, $admin);

        if ($request->boolean('remember')) {
            $this->issueRememberMeToken((int) $admin->admin_id);
        } else {
            $this->clearRememberMeToken((int) $admin->admin_id);
        }

        return redirect()->route('admin.dashboard');
    }

    /**
     * Display admin dashboard.
     */
    public function dashboard(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.admin_dashboard');
    }

    /**
     * Display user management page.
     */
    public function users(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.user_management');
    }

    /**
     * Display appointment management page.
     */
    public function appointments(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.appointment_management');
    }

    /**
     * Display donation records page.
     */
    public function donationRecords(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.donor_records');
    }

    /**
     * Display blood availability mapping page.
     */
    public function bloodAvailabilityMapping(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.blood_availability_mapping');
    }

    /**
     * Display notification center page.
     */
    public function notificationCenter(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.notification_center');
    }

    /**
     * Display report and analytics page.
     */
    public function reportAnalytics(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.report_analytics');
    }

    /**
     * Display audit logs page.
     */
    public function auditLogs(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.audit_log');
    }

    /**
     * Display RBAC management page.
     */
    public function rbac(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.rbac');
    }

    /**
     * Display settings page.
     */
    public function settings(Request $request)
    {
        if (!$this->hasActiveAdminSession($request)) {
            return redirect()->route('admin.login')->with('error', 'Please log in as admin to continue.');
        }

        return view('admin.settings');
    }

    /**
     * Check if admin has active session or valid remember-me token.
     */
    private function hasActiveAdminSession(Request $request): bool
    {
        if ($request->session()->has('admin_id')) {
            return true;
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

        $this->setAdminSession($request, $admin);
        $this->issueRememberMeToken((int) $admin->admin_id);

        return true;
    }

    /**
     * Set authenticated admin data in session.
     */
    private function setAdminSession(Request $request, object $admin): void
    {
        $request->session()->regenerate();
        $request->session()->put([
            'admin_id' => $admin->admin_id,
            'admin_username' => $admin->username,
            'admin_full_name' => $admin->full_name,
            'admin_role' => $admin->role,
        ]);
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
