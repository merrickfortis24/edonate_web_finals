<?php

namespace App\Http\Controllers;

use App\Mail\AdminPasswordResetMail;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
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
use Illuminate\Validation\Rule;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use App\Http\Controllers\Controller as BaseController;

class AdminAuthController extends BaseController
{
    private const SECURITY_SETTINGS_TABLE = 'admin_security_settings';
    private const SECURITY_DEFAULT_TWO_FACTOR_REQUIRED = true;
    private const SECURITY_DEFAULT_SESSION_TIMEOUT_MINUTES = 10;
    private const AUDIT_SECURITY_POLICY_ACTION = 'security_policy_update';
    private const AUDIT_SECURITY_POLICY_MODULE = 'security';
    private const REMEMBER_COOKIE_NAME = 'admin_remember';
    private const REMEMBER_DAYS = 30;
    private const RESET_TOKEN_TTL_MINUTES = 30;
    private const RESET_CACHE_PREFIX = 'admin_password_reset:';
    private const RESET_CACHE_STORE = 'file';
    private const TWO_FACTOR_PENDING_SESSION_KEY = 'pending_admin_2fa';
    private const TWO_FACTOR_SETUP_SECRET_SESSION_KEY = 'admin_2fa_setup_secret';
    private const TWO_FACTOR_PENDING_TTL_MINUTES = 5;
    private const TWO_FACTOR_MAX_ATTEMPTS = 5;
    private const TWO_FACTOR_TOTP_WINDOW = 1;
    private const TWO_FACTOR_RECOVERY_CODES_COUNT = 8;
    private const FIREBASE_SECURITY_EVENTS_DEFAULT_PATH = 'admin_security_events';

    /**
     * Display admin login page.
     */
    public function create(Request $request)
    {
        if ($this->hasActiveAdminSession($request)) {
            $dashboardRoute = $this->dashboardRouteForRole((string) $request->session()->get('admin_role', ''));
            $twoFactorSetupModal = $this->buildDashboardTwoFactorModalData($request);

            $requiresEnrollment = (bool) ($twoFactorSetupModal['required'] ?? false);
            $hasRecoveryCodes = is_array($twoFactorSetupModal['recoveryCodes'] ?? null)
                && ($twoFactorSetupModal['recoveryCodes'] ?? []) !== [];

            if ($requiresEnrollment || $hasRecoveryCodes) {
                return view('admin.admin_login', [
                    'twoFactorSetupModal' => $twoFactorSetupModal,
                    'postLoginDashboardUrl' => route($dashboardRoute),
                ]);
            }

            return redirect()->route($dashboardRoute);
        }

        $twoFactorChallengeModal = $this->buildLoginTwoFactorChallengeModalData($request);
        if ((bool) ($twoFactorChallengeModal['show'] ?? false)) {
            return view('admin.admin_login', [
                'twoFactorChallengeModal' => $twoFactorChallengeModal,
            ]);
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
            $this->pushFirebaseSecurityEvent('admin_login_password_failed', [
                'email' => Str::lower(trim((string) $validated['email'])),
                'ip' => $request->ip(),
            ]);

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

        $globalTwoFactorRequired = $this->isGlobalTwoFactorRequired();
        $accountTwoFactorEnabled = $this->isTwoFactorEnabledForAdmin($admin);

        if ($accountTwoFactorEnabled) {
            $this->stagePendingTwoFactorLogin(
                $request,
                $admin,
                $role,
                $request->boolean('remember')
            );

            $this->pushFirebaseSecurityEvent('admin_login_password_passed', [
                'admin_id' => (int) ($admin->admin_id ?? 0),
                'email' => Str::lower(trim((string) ($admin->email ?? ''))),
                'ip' => $request->ip(),
            ]);

            return redirect()
                ->route('admin.login')
                ->with('success', 'Enter your Google Authenticator code to continue.');
        }

        $this->clearPendingTwoFactorLogin($request);

        $this->setAdminSession($request, $admin, $role);

        if ($globalTwoFactorRequired && $this->supportsTwoFactorStorage() && !$accountTwoFactorEnabled) {
            $this->clearRememberMeToken((int) $admin->admin_id);

            $this->pushFirebaseSecurityEvent('admin_2fa_enrollment_required', [
                'admin_id' => (int) ($admin->admin_id ?? 0),
                'email' => Str::lower(trim((string) ($admin->email ?? ''))),
                'ip' => $request->ip(),
            ]);

            return redirect()
                ->route('admin.login')
                ->with('warning', 'Set up Google Authenticator to continue.');
        }

        if ($request->boolean('remember')) {
            $this->issueRememberMeToken((int) $admin->admin_id);
        } else {
            $this->clearRememberMeToken((int) $admin->admin_id);
        }

        return redirect()->route($this->dashboardRouteForRole($role));
    }

    /**
     * Cancel pending admin 2FA challenge and return to plain login state.
     */
    public function cancelTwoFactorChallenge(Request $request): RedirectResponse
    {
        $this->clearPendingTwoFactorLogin($request);

        return redirect()
            ->route('admin.login')
            ->with('success', 'Two-factor verification was cancelled. You may sign in again.');
    }

    /**
     * Verify admin Google Authenticator (or recovery) code and complete login.
     */
    public function verifyTwoFactorChallenge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'digits:6', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:64', 'required_without:code'],
        ]);

        $pending = $this->getPendingTwoFactorLogin($request);
        if ($pending === null) {
            return redirect()
                ->route('admin.login')
                ->with('error', 'Your login verification session has expired. Please log in again.');
        }

        $attempts = (int) ($pending['attempts'] ?? 0);
        if ($attempts >= self::TWO_FACTOR_MAX_ATTEMPTS) {
            $this->clearPendingTwoFactorLogin($request);

            return redirect()
                ->route('admin.login')
                ->with('error', 'Too many invalid 2FA attempts. Please log in again.');
        }

        $adminId = (int) ($pending['admin_id'] ?? 0);
        $admin = DB::table('admins')
            ->where('admin_id', $adminId)
            ->first();

        if (!$admin || !$this->isSupportedRole((string) ($admin->role ?? ''))) {
            $this->clearPendingTwoFactorLogin($request);

            return redirect()
                ->route('admin.login')
                ->with('error', 'Your account is no longer available for this login attempt.');
        }

        if (!$this->isTwoFactorEnabledForAdmin($admin)) {
            $this->clearPendingTwoFactorLogin($request);

            return redirect()
                ->route('admin.login')
                ->with('error', 'Two-factor authentication is not configured for this account. Please log in again.');
        }

        $code = trim((string) ($validated['code'] ?? ''));
        $recoveryCode = trim((string) ($validated['recovery_code'] ?? ''));

        $isValid = false;
        $usedRecoveryCode = false;

        if ($code !== '') {
            $decryptedSecret = $this->decryptTwoFactorSecret((string) ($admin->two_factor_secret ?? ''));
            $isValid = $decryptedSecret !== null
                && $this->verifyTotpCode($decryptedSecret, $code);
        } elseif ($recoveryCode !== '') {
            $isValid = $this->consumeRecoveryCode($admin, $recoveryCode);
            $usedRecoveryCode = $isValid;
        }

        if (!$isValid) {
            $pending['attempts'] = $attempts + 1;
            $request->session()->put(self::TWO_FACTOR_PENDING_SESSION_KEY, $pending);

            $this->pushFirebaseSecurityEvent('admin_2fa_failed', [
                'admin_id' => (int) ($admin->admin_id ?? 0),
                'ip' => $request->ip(),
                'attempt' => (int) $pending['attempts'],
            ]);

            return back()->withErrors([
                'code' => 'Invalid authentication code. Please try again.',
            ]);
        }

        $this->clearPendingTwoFactorLogin($request);

        $role = $this->normalizeRole((string) ($pending['role'] ?? ($admin->role ?? '')));
        $this->setAdminSession($request, $admin, $role);

        if ($this->supportsTwoFactorStorage()) {
            DB::table('admins')
                ->where('admin_id', (int) $admin->admin_id)
                ->update([
                    'two_factor_last_verified_at' => now(),
                ]);
        }

        if (!empty($pending['remember'])) {
            $this->issueRememberMeToken((int) $admin->admin_id);
        } else {
            $this->clearRememberMeToken((int) $admin->admin_id);
        }

        $this->logRbacAdminAudit(
            $request,
            'login',
            'Completed admin login with two-factor authentication.',
            (int) $admin->admin_id,
            [
                'two_factor_method' => $usedRecoveryCode ? 'recovery_code' : 'totp',
            ]
        );

        $this->pushFirebaseSecurityEvent('admin_2fa_success', [
            'admin_id' => (int) ($admin->admin_id ?? 0),
            'method' => $usedRecoveryCode ? 'recovery_code' : 'totp',
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route($this->dashboardRouteForRole($role))
            ->with('success', 'Two-factor authentication successful.');
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
        return view('admin.audit_log', [
            'auditApi' => [
                'listUrl' => route('admin.audit-logs.data'),
                'exportUrl' => route('admin.audit-logs.export'),
            ],
        ]);
    }

    /**
     * Return filtered, paginated audit logs for the audit page.
     */
    public function listAuditLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'action_type' => ['nullable', 'string', 'max:50'],
            'user_type' => ['nullable', 'string', Rule::in(['', 'admin', 'donor', 'system', 'other'])],
            'security_policy_only' => ['nullable', 'boolean'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $actionType = Str::lower(trim((string) ($validated['action_type'] ?? '')));
        $userType = Str::lower(trim((string) ($validated['user_type'] ?? '')));
        $securityPolicyOnly = (bool) ($validated['security_policy_only'] ?? false);

        $baseQuery = DB::table('audit_logs');
        $this->applyAuditLogFilters($baseQuery, $searchTerm, $actionType, $userType, $securityPolicyOnly);

        $statsRows = (clone $baseQuery)
            ->selectRaw("LOWER(COALESCE(result, 'success')) as result_key, COUNT(*) as total")
            ->groupBy('result_key')
            ->pluck('total', 'result_key');

        $paginator = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('audit_log_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $actionOptions = DB::table('audit_logs')
            ->select('action_type')
            ->whereNotNull('action_type')
            ->where('action_type', '!=', '')
            ->distinct()
            ->orderBy('action_type')
            ->pluck('action_type')
            ->map(fn (string $value): array => [
                'value' => Str::lower(trim($value)),
                'label' => $this->labelizeAuditValue($value),
            ])
            ->values()
            ->all();

        $roleValues = DB::table('audit_logs')
            ->select('actor_role')
            ->whereNotNull('actor_role')
            ->where('actor_role', '!=', '')
            ->distinct()
            ->pluck('actor_role')
            ->map(fn (string $value): string => $this->resolveAuditUserType($value))
            ->unique()
            ->values()
            ->all();

        $userOptions = collect($roleValues)
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => Str::title($value),
            ])
            ->sortBy('label')
            ->values()
            ->all();

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (object $entry) => $this->transformAuditLogEntry($entry))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'stats' => [
                'total' => (int) $paginator->total(),
                'success' => (int) ($statsRows['success'] ?? 0),
                'failed' => (int) ($statsRows['failed'] ?? 0),
                'warning' => (int) ($statsRows['warning'] ?? 0),
            ],
            'filters' => [
                'actions' => array_merge([
                    ['value' => '', 'label' => 'All Actions'],
                ], $actionOptions),
                'users' => array_merge([
                    ['value' => '', 'label' => 'All Users'],
                ], $userOptions),
            ],
        ]);
    }

    /**
     * Export filtered audit logs to CSV.
     */
    public function exportAuditLogsCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'action_type' => ['nullable', 'string', 'max:50'],
            'user_type' => ['nullable', 'string', Rule::in(['', 'admin', 'donor', 'system', 'other'])],
            'security_policy_only' => ['nullable', 'boolean'],
        ]);

        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $actionType = Str::lower(trim((string) ($validated['action_type'] ?? '')));
        $userType = Str::lower(trim((string) ($validated['user_type'] ?? '')));
        $securityPolicyOnly = (bool) ($validated['security_policy_only'] ?? false);

        $query = DB::table('audit_logs');
        $this->applyAuditLogFilters($query, $searchTerm, $actionType, $userType, $securityPolicyOnly);

        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('audit_log_id')
            ->get();

        $fileName = 'audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Timestamp',
                'User Name',
                'User Role',
                'Action',
                'Description',
                'Module',
                'Target Table',
                'Target ID',
                'IP Address',
                'Result',
                'Metadata',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    (string) ($row->created_at ?? ''),
                    (string) ($row->actor_name ?? ''),
                    (string) ($row->actor_role ?? ''),
                    (string) ($row->action_type ?? ''),
                    (string) ($row->description ?? ''),
                    (string) ($row->module_type ?? ''),
                    (string) ($row->target_table ?? ''),
                    (string) ($row->target_id ?? ''),
                    (string) ($row->ip_address ?? ''),
                    (string) ($row->result ?? ''),
                    (string) ($row->metadata ?? ''),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Display RBAC management page.
     */
    public function rbac(Request $request)
    {
        return view('admin.rbac', [
            'rbacUsers' => [],
            'rbacApi' => [
                'listUsersUrl' => route('admin.rbac.users.index'),
                'createUserUrl' => route('admin.rbac.users.store'),
                'updateUserUrlTemplate' => route('admin.rbac.users.update', ['admin' => '__ADMIN_ID__']),
                'deleteUserUrlTemplate' => route('admin.rbac.users.delete', ['admin' => '__ADMIN_ID__']),
                'resetUserPasswordUrlTemplate' => route('admin.rbac.users.password.reset', ['admin' => '__ADMIN_ID__']),
                'updateUserRoleUrlTemplate' => route('admin.rbac.users.role.update', ['admin' => '__ADMIN_ID__']),
                'currentAdminId' => (int) $request->session()->get('admin_id', 0),
                'csrfToken' => csrf_token(),
            ],
        ]);
    }

    /**
     * Return server-side paginated and sorted admin users for RBAC users tab.
     */
    public function listRbacUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'sort_by' => ['nullable', 'string', Rule::in(['admin_id', 'name', 'username', 'email', 'role', 'created_at'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 6);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');

        $query = DB::table('admins')
            ->select(array_merge($this->rbacAdminSelectColumns(), ['created_at']));

        if ($searchTerm !== '') {
            $query->where(function ($builder) use ($searchTerm) {
                $likeTerm = '%'.$searchTerm.'%';

                $builder->where('full_name', 'like', $likeTerm)
                    ->orWhere('username', 'like', $likeTerm)
                    ->orWhere('email', 'like', $likeTerm)
                    ->orWhere('role', 'like', $likeTerm);
            });
        }

        if ($sortBy === 'name') {
            $query->orderByRaw("COALESCE(NULLIF(full_name, ''), username) {$sortDir}");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        if ($sortBy !== 'admin_id') {
            $query->orderBy('admin_id', 'desc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $totalUsers = (int) DB::table('admins')->count();
        $roleCountsRaw = DB::table('admins')
            ->select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (object $admin) => $this->transformRbacAdminUser($admin))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'search' => $searchTerm,
            ],
            'summary' => [
                'total_users' => $totalUsers,
                'total_assignments' => $totalUsers,
                'role_user_counts' => [
                    '1' => (int) ($roleCountsRaw['admin'] ?? 0),
                    '2' => (int) ($roleCountsRaw['staff'] ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Create an admin account from RBAC users tab.
     */
    public function storeRbacUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['nullable', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:100', Rule::unique('admins', 'username')],
            'email' => ['required', 'email', 'max:150', Rule::unique('admins', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'role_id' => ['required', 'integer', 'in:1,2'],
        ]);

        $fullName = trim((string) ($validated['full_name'] ?? ''));
        $username = trim((string) $validated['username']);
        $email = Str::lower(trim((string) $validated['email']));
        $role = $this->roleNameFromRoleId((int) $validated['role_id']);

        $newAdminId = DB::table('admins')->insertGetId([
            'full_name' => $fullName === '' ? null : $fullName,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make((string) $validated['password']),
            'role' => $role,
            'created_at' => now(),
        ]);

        $createdAdmin = DB::table('admins')
            ->select($this->rbacAdminSelectColumns())
            ->where('admin_id', (int) $newAdminId)
            ->first();

        $displayName = $createdAdmin
            ? $this->displayNameForAdmin($createdAdmin)
            : ('Admin #'.(int) $newAdminId);

        $this->logRbacAdminAudit(
            $request,
            'create',
            'Created admin account: '.$displayName,
            (int) $newAdminId,
            [
                'username' => $username,
                'email' => $email,
                'role' => $role,
            ]
        );

        return response()->json([
            'message' => 'Admin account created successfully.',
            'user' => $createdAdmin ? $this->transformRbacAdminUser($createdAdmin) : null,
        ], 201);
    }

    /**
     * Update an admin account profile from RBAC users tab.
     */
    public function updateRbacUser(Request $request, int $adminId): JsonResponse
    {
        $targetAdmin = DB::table('admins')
            ->select($this->rbacAdminSelectColumns())
            ->where('admin_id', $adminId)
            ->first();

        if (!$targetAdmin) {
            return response()->json([
                'message' => 'Admin user not found.',
            ], 404);
        }

        $validated = $request->validate([
            'full_name' => ['nullable', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:100', Rule::unique('admins', 'username')->ignore($adminId, 'admin_id')],
            'email' => ['required', 'email', 'max:150', Rule::unique('admins', 'email')->ignore($adminId, 'admin_id')],
            'role_id' => ['required', 'integer', 'in:1,2'],
        ]);

        $newRole = $this->roleNameFromRoleId((int) $validated['role_id']);
        $currentAdminId = (int) $request->session()->get('admin_id');

        // Prevent accidental self-demotion that would lock the current admin out of RBAC.
        if ($currentAdminId === (int) $targetAdmin->admin_id && $newRole !== 'admin') {
            return response()->json([
                'message' => 'You cannot change your own account role to staff from this page.',
            ], 422);
        }

        $fullName = trim((string) ($validated['full_name'] ?? ''));
        $updatePayload = [
            'full_name' => $fullName === '' ? null : $fullName,
            'username' => trim((string) $validated['username']),
            'email' => Str::lower(trim((string) $validated['email'])),
            'role' => $newRole,
        ];

        $changes = [];
        foreach (['full_name', 'username', 'email', 'role'] as $field) {
            $before = (string) ($targetAdmin->{$field} ?? '');
            $after = (string) ($updatePayload[$field] ?? '');

            if ($before !== $after) {
                $changes[$field] = ['from' => $before, 'to' => $after];
            }
        }

        DB::table('admins')
            ->where('admin_id', (int) $targetAdmin->admin_id)
            ->update($updatePayload);

        $updatedAdmin = DB::table('admins')
            ->select($this->rbacAdminSelectColumns())
            ->where('admin_id', (int) $targetAdmin->admin_id)
            ->first();

        if ($updatedAdmin) {
            $this->syncCurrentAdminSessionProfile($request, $updatedAdmin);
        }

        $this->logRbacAdminAudit(
            $request,
            'update',
            'Updated admin account: '.$this->displayNameForAdmin($updatedAdmin ?: $targetAdmin),
            (int) $targetAdmin->admin_id,
            [
                'changes' => $changes,
            ]
        );

        return response()->json([
            'message' => 'Admin user updated successfully.',
            'user' => $this->transformRbacAdminUser($updatedAdmin ?: $targetAdmin),
        ]);
    }

    /**
     * Delete an admin account from RBAC users tab.
     */
    public function deleteRbacUser(Request $request, int $adminId): JsonResponse
    {
        $targetAdmin = DB::table('admins')
            ->select($this->rbacAdminSelectColumns())
            ->where('admin_id', $adminId)
            ->first();

        if (!$targetAdmin) {
            return response()->json([
                'message' => 'Admin user not found.',
            ], 404);
        }

        $currentAdminId = (int) $request->session()->get('admin_id');
        if ($currentAdminId === (int) $targetAdmin->admin_id) {
            return response()->json([
                'message' => 'You cannot delete your own account while logged in.',
            ], 422);
        }

        $targetDisplayName = $this->displayNameForAdmin($targetAdmin);

        DB::table('admins')
            ->where('admin_id', (int) $targetAdmin->admin_id)
            ->delete();

        $this->logRbacAdminAudit(
            $request,
            'delete',
            'Deleted admin account: '.$targetDisplayName,
            (int) $targetAdmin->admin_id,
            [
                'email' => (string) ($targetAdmin->email ?? ''),
                'username' => (string) ($targetAdmin->username ?? ''),
                'role' => (string) ($targetAdmin->role ?? ''),
            ]
        );

        return response()->json([
            'message' => 'Admin user deleted successfully.',
            'deletedUserId' => (int) $targetAdmin->admin_id,
        ]);
    }

    /**
     * Update an admin user's role from RBAC users tab.
     */
    public function updateRbacUserRole(Request $request, int $adminId): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'in:1,2'],
        ]);

        $targetAdmin = DB::table('admins')
            ->select($this->rbacAdminSelectColumns())
            ->where('admin_id', $adminId)
            ->first();

        if (!$targetAdmin) {
            return response()->json([
                'message' => 'Admin user not found.',
            ], 404);
        }

        $newRole = $this->roleNameFromRoleId((int) $validated['role_id']);
        $currentAdminId = (int) $request->session()->get('admin_id');

        // Prevent accidental self-demotion that would lock the current admin out of RBAC.
        if ($currentAdminId === (int) $targetAdmin->admin_id && $newRole !== 'admin') {
            return response()->json([
                'message' => 'You cannot change your own account role to staff from this page.',
            ], 422);
        }

        DB::table('admins')
            ->where('admin_id', (int) $targetAdmin->admin_id)
            ->update([
                'role' => $newRole,
            ]);

        $updatedAdmin = DB::table('admins')
            ->select($this->rbacAdminSelectColumns())
            ->where('admin_id', (int) $targetAdmin->admin_id)
            ->first();

        if ($updatedAdmin) {
            $this->syncCurrentAdminSessionProfile($request, $updatedAdmin);
        }

        $this->logRbacAdminAudit(
            $request,
            'update',
            'Updated admin role for '.$this->displayNameForAdmin($updatedAdmin ?: $targetAdmin),
            (int) $targetAdmin->admin_id,
            [
                'role_from' => (string) ($targetAdmin->role ?? ''),
                'role_to' => $newRole,
            ]
        );

        return response()->json([
            'message' => 'Admin user role updated successfully.',
            'user' => $this->transformRbacAdminUser($updatedAdmin ?: $targetAdmin),
        ]);
    }

    /**
     * Reset an admin user's password using a dedicated RBAC action.
     */
    public function resetRbacUserPassword(Request $request, int $adminId): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $targetAdmin = DB::table('admins')
            ->select($this->rbacAdminSelectColumns())
            ->where('admin_id', $adminId)
            ->first();

        if (!$targetAdmin) {
            return response()->json([
                'message' => 'Admin user not found.',
            ], 404);
        }

        DB::table('admins')
            ->where('admin_id', (int) $targetAdmin->admin_id)
            ->update([
                'password' => Hash::make((string) $validated['password']),
                'remember_token' => null,
                'remember_token_expires_at' => null,
            ]);

        $this->logRbacAdminAudit(
            $request,
            'update',
            'Reset password for admin account: '.$this->displayNameForAdmin($targetAdmin),
            (int) $targetAdmin->admin_id,
            [
                'email' => (string) ($targetAdmin->email ?? ''),
                'username' => (string) ($targetAdmin->username ?? ''),
            ]
        );

        return response()->json([
            'message' => 'Admin password reset successfully.',
        ]);
    }

    /**
     * Display settings page.
     */
    public function settings(Request $request)
    {
        $adminId = (int) $request->session()->get('admin_id', 0);
        $columns = ['admin_id'];

        if ($this->supportsTwoFactorStorage()) {
            $columns = array_merge($columns, [
                'two_factor_enabled',
                'two_factor_confirmed_at',
                'two_factor_secret',
            ]);
        }

        $admin = DB::table('admins')
            ->select($columns)
            ->where('admin_id', $adminId)
            ->first();

        $currentAccountTwoFactorEnabled = $this->isTwoFactorEnabledForAdmin($admin);
        $globalSecuritySettings = $this->getGlobalSecuritySettings();

        return view('admin.settings', [
            'settingsPayload' => [
                'page' => 'settings',
                'settings' => [
                    'general' => [
                        'systemName' => 'eDonate',
                        'systemEmail' => 'admin@edonate.local',
                        'contactNumber' => '+63 917 123 4567',
                    ],
                    'notifications' => [
                        'email' => true,
                        'sms' => false,
                    ],
                    'security' => [
                        'twoFactor' => (bool) ($globalSecuritySettings['two_factor_required'] ?? self::SECURITY_DEFAULT_TWO_FACTOR_REQUIRED),
                        'sessionTimeout' => (string) ($globalSecuritySettings['session_timeout_minutes'] ?? self::SECURITY_DEFAULT_SESSION_TIMEOUT_MINUTES),
                        'twoFactorSetupUrl' => route('admin.2fa.setup'),
                        'updateSecurityUrl' => route('admin.settings.security.update'),
                        'currentAccountTwoFactorEnabled' => $currentAccountTwoFactorEnabled,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Persist admin security settings from settings page.
     */
    public function updateSecuritySettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'two_factor_required' => ['required', 'boolean'],
            'session_timeout' => ['required', 'integer', Rule::in([5, 10, 30])],
        ]);

        if (!$this->supportsGlobalSecuritySettingsStorage()) {
            return response()->json([
                'message' => 'Security settings storage is not available. Please run migrations first.',
            ], 422);
        }

        $this->upsertGlobalSecuritySettings(
            (bool) $validated['two_factor_required'],
            (int) $validated['session_timeout']
        );

        $admin = $this->getCurrentAdminForTwoFactor($request);
        $currentAccountTwoFactorEnabled = $this->isTwoFactorEnabledForAdmin($admin);
        $requiresEnrollment = (bool) $validated['two_factor_required'] && !$currentAccountTwoFactorEnabled;

        $adminId = (int) ($request->session()->get('admin_id', 0));
        $this->logAdminSecurityPolicyAudit(
            $request,
            'Updated global admin security settings.',
            [
                'two_factor_required' => (bool) $validated['two_factor_required'],
                'session_timeout' => (int) $validated['session_timeout'],
            ]
        );

        $this->pushFirebaseSecurityEvent('admin_security_settings_updated', [
            'admin_id' => $adminId,
            'two_factor_required' => (bool) $validated['two_factor_required'],
            'session_timeout' => (int) $validated['session_timeout'],
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Security settings saved successfully.',
            'security' => [
                'twoFactor' => (bool) $validated['two_factor_required'],
                'sessionTimeout' => (string) ((int) $validated['session_timeout']),
                'twoFactorSetupUrl' => route('admin.2fa.setup'),
                'updateSecurityUrl' => route('admin.settings.security.update'),
                'currentAccountTwoFactorEnabled' => $currentAccountTwoFactorEnabled,
            ],
            'requiresTwoFactorEnrollment' => $requiresEnrollment,
        ]);
    }

    /**
     * Display Google Authenticator setup and management page.
     */
    public function setupTwoFactor(Request $request)
    {
        $admin = $this->getCurrentAdminForTwoFactor($request);
        if ($admin === null) {
            return redirect()
                ->route('admin.login')
                ->with('error', 'Please log in to continue.');
        }

        if (!$this->supportsTwoFactorStorage()) {
            return redirect()
                ->route('admin.settings')
                ->with('error', 'Two-factor columns are not available yet. Please run migrations first.');
        }

        return view('admin.admin_2fa_setup', $this->prepareTwoFactorSetupViewData($request, $admin));
    }

    /**
     * Prepare shared Google Authenticator setup payload for authenticated admin.
     *
     * @return array<string, mixed>
     */
    private function prepareTwoFactorSetupViewData(Request $request, object $admin): array
    {
        $twoFactorEnabled = $this->isTwoFactorEnabledForAdmin($admin);
        $secret = null;
        $qrSvg = null;
        $provisioningUri = null;

        if (!$twoFactorEnabled) {
            $secret = trim((string) $request->session()->get(self::TWO_FACTOR_SETUP_SECRET_SESSION_KEY, ''));
            if ($secret === '') {
                $secret = $this->google2fa()->generateSecretKey();
                $request->session()->put(self::TWO_FACTOR_SETUP_SECRET_SESSION_KEY, $secret);
            }

            $issuer = (string) config('app.name', 'eDonate');
            $email = trim((string) ($admin->email ?? ''));
            $provisioningUri = $this->google2fa()->getQRCodeUrl($issuer, $email, $secret);
            $qrSvg = $this->generateSvgQrCode($provisioningUri);
        }

        return [
            'twoFactorEnabled' => $twoFactorEnabled,
            'maskedEmail' => $this->maskEmail((string) ($admin->email ?? '')),
            'secret' => $secret,
            'provisioningUri' => $provisioningUri,
            'qrSvg' => $qrSvg,
            'confirmedAt' => !empty($admin->two_factor_confirmed_at)
                ? Carbon::parse((string) $admin->two_factor_confirmed_at)
                : null,
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes', []),
        ];
    }

    /**
     * Build dashboard modal payload when global policy requires 2FA enrollment.
     *
     * @return array<string, mixed>
     */
    private function buildDashboardTwoFactorModalData(Request $request): array
    {
        $defaultPayload = [
            'required' => false,
            'maskedEmail' => '',
            'secret' => null,
            'qrSvg' => null,
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes', []),
        ];

        if (!$this->isGlobalTwoFactorRequired() || !$this->supportsTwoFactorStorage()) {
            return $defaultPayload;
        }

        $admin = $this->getCurrentAdminForTwoFactor($request);
        if ($admin === null) {
            return $defaultPayload;
        }

        $setupData = $this->prepareTwoFactorSetupViewData($request, $admin);

        return [
            'required' => !(bool) ($setupData['twoFactorEnabled'] ?? false),
            'maskedEmail' => (string) ($setupData['maskedEmail'] ?? ''),
            'secret' => $setupData['secret'] ?? null,
            'qrSvg' => $setupData['qrSvg'] ?? null,
            'recoveryCodes' => is_array($setupData['recoveryCodes'] ?? null)
                ? $setupData['recoveryCodes']
                : [],
        ];
    }

    /**
     * Build login-page 2FA challenge modal payload from pending state.
     *
     * @return array<string, mixed>
     */
    private function buildLoginTwoFactorChallengeModalData(Request $request): array
    {
        $pending = $this->getPendingTwoFactorLogin($request);
        if ($pending === null) {
            return [
                'show' => false,
                'maskedEmail' => '',
                'remainingSeconds' => 0,
            ];
        }

        return [
            'show' => true,
            'maskedEmail' => $this->maskEmail((string) ($pending['email'] ?? '')),
            'remainingSeconds' => max(0, (int) ($pending['expires_at'] ?? 0) - now()->timestamp),
        ];
    }

    /**
     * Confirm 2FA setup by validating first Google Authenticator code.
     */
    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
            'return_to_dashboard' => ['nullable', 'boolean'],
        ]);

        $admin = $this->getCurrentAdminForTwoFactor($request);
        if ($admin === null) {
            return redirect()
                ->route('admin.login')
                ->with('error', 'Please log in to continue.');
        }

        if (!$this->supportsTwoFactorStorage()) {
            return redirect()
                ->route('admin.settings')
                ->with('error', 'Two-factor columns are not available yet. Please run migrations first.');
        }

        $secret = trim((string) $request->session()->get(self::TWO_FACTOR_SETUP_SECRET_SESSION_KEY, ''));
        if ($secret === '') {
            return redirect()
                ->route('admin.2fa.setup')
                ->with('error', '2FA setup session expired. Please scan the QR code again.');
        }

        if (!$this->verifyTotpCode($secret, (string) $validated['otp'])) {
            return back()->withErrors([
                'otp' => 'Invalid authenticator code. Please try again.',
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $hashedRecoveryCodes = collect($recoveryCodes)
            ->map(fn (string $value): string => Hash::make($value))
            ->values()
            ->all();

        DB::table('admins')
            ->where('admin_id', (int) $admin->admin_id)
            ->update([
                'two_factor_enabled' => true,
                'two_factor_secret' => Crypt::encryptString($secret),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => json_encode($hashedRecoveryCodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

        $request->session()->forget(self::TWO_FACTOR_SETUP_SECRET_SESSION_KEY);
        $request->session()->flash('two_factor_recovery_codes', $recoveryCodes);

        $this->logRbacAdminAudit(
            $request,
            'update',
            'Enabled Google Authenticator two-factor authentication.',
            (int) $admin->admin_id,
            [
                'security_event' => 'two_factor_enabled',
            ]
        );

        $this->pushFirebaseSecurityEvent('admin_2fa_enabled', [
            'admin_id' => (int) ($admin->admin_id ?? 0),
            'ip' => $request->ip(),
        ]);

        if ((bool) ($validated['return_to_dashboard'] ?? false)) {
            return redirect()
                ->route('admin.login')
                ->with('success', 'Google Authenticator has been enabled. Save your recovery codes below.');
        }

        return redirect()
            ->route('admin.2fa.setup')
            ->with('success', 'Google Authenticator has been enabled. Save your recovery codes below.');
    }

    /**
     * Disable Google Authenticator 2FA for the current admin account.
     */
    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
        ]);

        $admin = $this->getCurrentAdminForTwoFactor($request, true);
        if ($admin === null) {
            return redirect()
                ->route('admin.login')
                ->with('error', 'Please log in to continue.');
        }

        if (!$this->isTwoFactorEnabledForAdmin($admin)) {
            return redirect()
                ->route('admin.2fa.setup')
                ->with('error', 'Two-factor authentication is already disabled.');
        }

        if (!Hash::check((string) $validated['current_password'], (string) ($admin->password ?? ''))) {
            return back()->withErrors([
                'current_password' => 'Current password does not match.',
            ]);
        }

        $secret = $this->decryptTwoFactorSecret((string) ($admin->two_factor_secret ?? ''));
        if ($secret === null || !$this->verifyTotpCode($secret, (string) $validated['otp'])) {
            return back()->withErrors([
                'otp' => 'Invalid authenticator code.',
            ]);
        }

        DB::table('admins')
            ->where('admin_id', (int) $admin->admin_id)
            ->update([
                'two_factor_enabled' => false,
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_last_verified_at' => null,
            ]);

        $request->session()->forget(self::TWO_FACTOR_SETUP_SECRET_SESSION_KEY);

        $this->logRbacAdminAudit(
            $request,
            'update',
            'Disabled Google Authenticator two-factor authentication.',
            (int) $admin->admin_id,
            [
                'security_event' => 'two_factor_disabled',
            ]
        );

        $this->pushFirebaseSecurityEvent('admin_2fa_disabled', [
            'admin_id' => (int) ($admin->admin_id ?? 0),
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.2fa.setup')
            ->with('success', 'Two-factor authentication has been disabled.');
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

            $request->session()->forget([
                'admin_id',
                'admin_username',
                'admin_full_name',
                'admin_role',
                self::TWO_FACTOR_PENDING_SESSION_KEY,
                self::TWO_FACTOR_SETUP_SECRET_SESSION_KEY,
            ]);
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

        // Require explicit password + TOTP flow for 2FA-enabled accounts.
        if ($this->isTwoFactorEnabledForAdmin($admin)) {
            $this->clearRememberMeToken((int) $admin->admin_id);

            $this->pushFirebaseSecurityEvent('admin_remember_login_blocked_by_2fa', [
                'admin_id' => (int) ($admin->admin_id ?? 0),
                'ip' => $request->ip(),
            ]);

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
     * Resolve current admin record for 2FA actions.
     */
    private function getCurrentAdminForTwoFactor(Request $request, bool $includePassword = false): ?object
    {
        $adminId = (int) $request->session()->get('admin_id', 0);
        if ($adminId <= 0) {
            return null;
        }

        $columns = ['admin_id', 'email', 'username', 'full_name', 'role'];

        if ($this->supportsTwoFactorStorage()) {
            $columns = array_merge($columns, [
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
            ]);
        }

        if ($includePassword) {
            $columns[] = 'password';
        }

        return DB::table('admins')
            ->select($columns)
            ->where('admin_id', $adminId)
            ->first();
    }

    /**
     * Detect if admins table has required columns for 2FA.
     */
    private function supportsTwoFactorStorage(): bool
    {
        if (!Schema::hasTable('admins')) {
            return false;
        }

        return Schema::hasColumn('admins', 'two_factor_enabled')
            && Schema::hasColumn('admins', 'two_factor_secret')
            && Schema::hasColumn('admins', 'two_factor_confirmed_at')
            && Schema::hasColumn('admins', 'two_factor_recovery_codes')
            && Schema::hasColumn('admins', 'two_factor_last_verified_at');
    }

    /**
     * Resolve global security settings with safe defaults.
     *
     * @return array{two_factor_required: bool, session_timeout_minutes: int}
     */
    private function getGlobalSecuritySettings(): array
    {
        $defaults = [
            'two_factor_required' => self::SECURITY_DEFAULT_TWO_FACTOR_REQUIRED,
            'session_timeout_minutes' => self::SECURITY_DEFAULT_SESSION_TIMEOUT_MINUTES,
        ];

        if (!$this->supportsGlobalSecuritySettingsStorage()) {
            return $defaults;
        }

        $row = DB::table(self::SECURITY_SETTINGS_TABLE)
            ->select('enforce_two_factor', 'session_timeout_minutes')
            ->orderByDesc('admin_security_setting_id')
            ->first();

        if (!$row) {
            return $defaults;
        }

        return [
            'two_factor_required' => (bool) ($row->enforce_two_factor ?? $defaults['two_factor_required']),
            'session_timeout_minutes' => in_array((int) ($row->session_timeout_minutes ?? 0), [5, 10, 30], true)
                ? (int) $row->session_timeout_minutes
                : $defaults['session_timeout_minutes'],
        ];
    }

    /**
     * Determine if global policy requires 2FA enrollment for all admin/staff accounts.
     */
    private function isGlobalTwoFactorRequired(): bool
    {
        return (bool) ($this->getGlobalSecuritySettings()['two_factor_required'] ?? self::SECURITY_DEFAULT_TWO_FACTOR_REQUIRED);
    }

    /**
     * Persist global security settings in singleton-style table.
     */
    private function upsertGlobalSecuritySettings(bool $twoFactorRequired, int $sessionTimeout): void
    {
        if (!$this->supportsGlobalSecuritySettingsStorage()) {
            return;
        }

        $normalizedTimeout = in_array($sessionTimeout, [5, 10, 30], true)
            ? $sessionTimeout
            : self::SECURITY_DEFAULT_SESSION_TIMEOUT_MINUTES;

        $payload = [
            'enforce_two_factor' => $twoFactorRequired,
            'session_timeout_minutes' => $normalizedTimeout,
            'updated_at' => now(),
        ];

        $existingId = DB::table(self::SECURITY_SETTINGS_TABLE)
            ->orderByDesc('admin_security_setting_id')
            ->value('admin_security_setting_id');

        if (is_numeric($existingId)) {
            DB::table(self::SECURITY_SETTINGS_TABLE)
                ->where('admin_security_setting_id', (int) $existingId)
                ->update($payload);

            return;
        }

        DB::table(self::SECURITY_SETTINGS_TABLE)
            ->insert(array_merge($payload, [
                'created_at' => now(),
            ]));
    }

    /**
     * Detect if global admin security settings table is available.
     */
    private function supportsGlobalSecuritySettingsStorage(): bool
    {
        if (!Schema::hasTable(self::SECURITY_SETTINGS_TABLE)) {
            return false;
        }

        return Schema::hasColumn(self::SECURITY_SETTINGS_TABLE, 'enforce_two_factor')
            && Schema::hasColumn(self::SECURITY_SETTINGS_TABLE, 'session_timeout_minutes');
    }

    /**
     * Determine if admin account currently enforces Google Authenticator.
     */
    private function isTwoFactorEnabledForAdmin(?object $admin): bool
    {
        if (!$this->supportsTwoFactorStorage() || !$admin) {
            return false;
        }

        return (bool) ($admin->two_factor_enabled ?? false)
            && trim((string) ($admin->two_factor_secret ?? '')) !== '';
    }

    /**
     * Stage pending 2FA challenge data in session after password validation.
     */
    private function stagePendingTwoFactorLogin(Request $request, object $admin, string $role, bool $rememberRequested): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::TWO_FACTOR_PENDING_SESSION_KEY, [
            'admin_id' => (int) ($admin->admin_id ?? 0),
            'email' => Str::lower(trim((string) ($admin->email ?? ''))),
            'role' => $this->normalizeRole($role),
            'remember' => $rememberRequested,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TWO_FACTOR_PENDING_TTL_MINUTES)->timestamp,
        ]);
    }

    /**
     * Read and validate pending 2FA login state.
     *
     * @return array<string, mixed>|null
     */
    private function getPendingTwoFactorLogin(Request $request): ?array
    {
        $pending = $request->session()->get(self::TWO_FACTOR_PENDING_SESSION_KEY);

        if (!is_array($pending) || !is_numeric($pending['admin_id'] ?? null)) {
            return null;
        }

        $expiresAt = (int) ($pending['expires_at'] ?? 0);
        if ($expiresAt <= 0 || now()->timestamp > $expiresAt) {
            $this->clearPendingTwoFactorLogin($request);
            return null;
        }

        return $pending;
    }

    /**
     * Clear pending 2FA challenge state.
     */
    private function clearPendingTwoFactorLogin(Request $request): void
    {
        $request->session()->forget(self::TWO_FACTOR_PENDING_SESSION_KEY);
    }

    /**
     * Verify TOTP code against decrypted secret.
     */
    private function verifyTotpCode(string $secret, string $code): bool
    {
        $normalizedCode = preg_replace('/\s+/', '', trim($code)) ?? '';
        if (!preg_match('/^\d{6}$/', $normalizedCode)) {
            return false;
        }

        return $this->google2fa()->verifyKey($secret, $normalizedCode, self::TWO_FACTOR_TOTP_WINDOW);
    }

    /**
     * Decrypt persisted 2FA secret safely.
     */
    private function decryptTwoFactorSecret(string $encryptedSecret): ?string
    {
        if (trim($encryptedSecret) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encryptedSecret);
        } catch (DecryptException $exception) {
            return null;
        }
    }

    /**
     * Generate one-time backup recovery codes for 2FA lockout scenarios.
     *
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($index = 0; $index < self::TWO_FACTOR_RECOVERY_CODES_COUNT; $index++) {
            $codes[] = Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5));
        }

        return $codes;
    }

    /**
     * Parse stored hashed recovery code payload from admins table.
     *
     * @return array<int, string>
     */
    private function parseRecoveryCodeHashes(object $admin): array
    {
        $raw = $admin->two_factor_recovery_codes ?? null;
        $decoded = null;

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? $value : '',
            $decoded
        )));
    }

    /**
     * Validate and consume a single recovery code.
     */
    private function consumeRecoveryCode(object $admin, string $recoveryCode): bool
    {
        if (!$this->supportsTwoFactorStorage()) {
            return false;
        }

        $normalizedInput = Str::upper(trim($recoveryCode));
        if ($normalizedInput === '') {
            return false;
        }

        $hashes = $this->parseRecoveryCodeHashes($admin);
        if ($hashes === []) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (!Hash::check($normalizedInput, $hash)) {
                continue;
            }

            unset($hashes[$index]);

            DB::table('admins')
                ->where('admin_id', (int) ($admin->admin_id ?? 0))
                ->update([
                    'two_factor_recovery_codes' => json_encode(array_values($hashes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

            return true;
        }

        return false;
    }

    /**
     * Render provisioning URI as an inline SVG QR code.
     */
    private function generateSvgQrCode(string $contents): string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle(220),
                new SvgImageBackEnd()
            );

            return (new Writer($renderer))->writeString($contents);
        } catch (Throwable $exception) {
            logger()->warning('Unable to generate 2FA QR code SVG.', [
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Mask email before displaying it on authentication screens.
     */
    private function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || !Str::contains($email, '@')) {
            return 'your account';
        }

        [$localPart, $domain] = explode('@', $email, 2);
        $localPart = trim($localPart);

        if (Str::length($localPart) <= 2) {
            $maskedLocalPart = Str::substr($localPart, 0, 1).'*';
        } else {
            $maskedLocalPart = Str::substr($localPart, 0, 2)
                .str_repeat('*', max(1, Str::length($localPart) - 2));
        }

        return $maskedLocalPart.'@'.$domain;
    }

    /**
     * Instantiate Google2FA service.
     */
    private function google2fa(): Google2FA
    {
        return new Google2FA();
    }

    /**
     * Push security-related admin auth events into Firebase Realtime Database.
     */
    private function pushFirebaseSecurityEvent(string $eventType, array $payload = []): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $path = trim((string) config('services.firebase.security_events_path', self::FIREBASE_SECURITY_EVENTS_DEFAULT_PATH), '/');
            if ($path === '') {
                $path = self::FIREBASE_SECURITY_EVENTS_DEFAULT_PATH;
            }

            $database->getReference($path)->push([
                'event_type' => $eventType,
                'app' => (string) config('app.name', 'eDonate'),
                'payload' => $payload,
                'created_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to push admin security event to Firebase.', [
                'event_type' => $eventType,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Build RBAC users payload from admins table.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRbacAdminUsers(): array
    {
        return DB::table('admins')
            ->select($this->rbacAdminSelectColumns())
            ->orderBy('admin_id')
            ->get()
            ->map(fn (object $admin) => $this->transformRbacAdminUser($admin))
            ->values()
            ->all();
    }

    /**
     * Normalize admin record for RBAC front-end shape.
     *
     * @return array<string, mixed>
     */
    private function transformRbacAdminUser(object $admin): array
    {
        $displayName = $this->displayNameForAdmin($admin);

        $role = $this->normalizeRole((string) ($admin->role ?? ''));
        $roleId = $role === 'admin' ? 1 : 2;
        $twoFactorEnrolled = false;

        if ($this->supportsTwoFactorStorage()) {
            $twoFactorEnrolled = (bool) ($admin->two_factor_enabled ?? false)
                && !empty($admin->two_factor_confirmed_at);
        }

        return [
            'id' => (int) $admin->admin_id,
            'name' => $displayName,
            'fullName' => (string) ($admin->full_name ?? ''),
            'username' => (string) ($admin->username ?? ''),
            'email' => (string) ($admin->email ?? ''),
            'roleIds' => [$roleId],
            'twoFactorEnrolled' => $twoFactorEnrolled,
        ];
    }

    /**
     * Resolve common select columns for RBAC admin user payloads.
     *
     * @return array<int, string>
     */
    private function rbacAdminSelectColumns(): array
    {
        $columns = ['admin_id', 'full_name', 'username', 'email', 'role'];

        if ($this->supportsTwoFactorStorage()) {
            $columns[] = 'two_factor_enabled';
            $columns[] = 'two_factor_confirmed_at';
        }

        return $columns;
    }

    /**
     * Resolve a readable admin display name from available columns.
     */
    private function displayNameForAdmin(object $admin): string
    {
        $displayName = trim((string) ($admin->full_name ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($admin->username ?? ''));
        }
        if ($displayName === '') {
            $displayName = 'Admin #'.(int) ($admin->admin_id ?? 0);
        }

        return $displayName;
    }

    /**
     * Persist an RBAC admin action to audit logs table when available.
     */
    private function logRbacAdminAudit(
        Request $request,
        string $actionType,
        string $description,
        ?int $targetAdminId = null,
        array $metadata = [],
        string $result = 'success'
    ): void {
        try {
            $actorName = trim((string) ($request->session()->get('admin_full_name') ?: $request->session()->get('admin_username') ?: 'Admin'));
            $actorRole = ucfirst($this->normalizeRole((string) $request->session()->get('admin_role', 'admin')));

            if (!Schema::hasTable('audit_logs')) {
                logger()->info('RBAC admin audit event', [
                    'action_type' => $actionType,
                    'description' => $description,
                    'target_admin_id' => $targetAdminId,
                    'metadata' => $metadata,
                ]);

                return;
            }

            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'action_type' => $actionType,
                'module_type' => 'rbac',
                'target_table' => 'admins',
                'target_id' => $targetAdminId,
                'description' => $description,
                'ip_address' => $request->ip(),
                'result' => $result,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to persist RBAC admin audit event.', [
                'action_type' => $actionType,
                'description' => $description,
                'target_admin_id' => $targetAdminId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Persist admin global security policy changes to audit logs table.
     */
    private function logAdminSecurityPolicyAudit(
        Request $request,
        string $description,
        array $metadata = [],
        string $result = 'success'
    ): void {
        try {
            $actorName = trim((string) ($request->session()->get('admin_full_name') ?: $request->session()->get('admin_username') ?: 'Admin'));
            $actorRole = ucfirst($this->normalizeRole((string) $request->session()->get('admin_role', 'admin')));

            if (!Schema::hasTable('audit_logs')) {
                logger()->info('Security policy audit event', [
                    'description' => $description,
                    'metadata' => $metadata,
                ]);

                return;
            }

            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'action_type' => self::AUDIT_SECURITY_POLICY_ACTION,
                'module_type' => self::AUDIT_SECURITY_POLICY_MODULE,
                'target_table' => self::SECURITY_SETTINGS_TABLE,
                'target_id' => null,
                'description' => $description,
                'ip_address' => $request->ip(),
                'result' => $result,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to persist security policy audit event.', [
                'description' => $description,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Apply shared audit log filters used by list and export endpoints.
     */
    private function applyAuditLogFilters(
        $query,
        string $searchTerm,
        string $actionType,
        string $userType,
        bool $securityPolicyOnly = false
    ): void
    {
        if ($searchTerm !== '') {
            $likeTerm = '%'.$searchTerm.'%';

            $query->where(function ($builder) use ($likeTerm): void {
                $builder->where('actor_name', 'like', $likeTerm)
                    ->orWhere('actor_role', 'like', $likeTerm)
                    ->orWhere('action_type', 'like', $likeTerm)
                    ->orWhere('description', 'like', $likeTerm)
                    ->orWhere('module_type', 'like', $likeTerm)
                    ->orWhere('ip_address', 'like', $likeTerm)
                    ->orWhere('target_table', 'like', $likeTerm);
            });
        }

        if ($actionType !== '') {
            $query->whereRaw('LOWER(action_type) = ?', [$actionType]);
        }

        if ($securityPolicyOnly) {
            $query->whereRaw("LOWER(COALESCE(target_table, '')) = ?", [self::SECURITY_SETTINGS_TABLE]);
        }

        if ($userType === 'admin') {
            $query->where(function ($builder): void {
                $builder->whereRaw("LOWER(COALESCE(actor_role, '')) like ?", ['%admin%'])
                    ->orWhereRaw("LOWER(COALESCE(actor_role, '')) like ?", ['%staff%']);
            });
            return;
        }

        if ($userType === 'donor') {
            $query->whereRaw("LOWER(COALESCE(actor_role, '')) like ?", ['%donor%']);
            return;
        }

        if ($userType === 'system') {
            $query->whereRaw("LOWER(COALESCE(actor_role, '')) like ?", ['%system%']);
            return;
        }

        if ($userType === 'other') {
            $query->whereRaw("LOWER(COALESCE(actor_role, '')) not like ?", ['%admin%'])
                ->whereRaw("LOWER(COALESCE(actor_role, '')) not like ?", ['%staff%'])
                ->whereRaw("LOWER(COALESCE(actor_role, '')) not like ?", ['%donor%'])
                ->whereRaw("LOWER(COALESCE(actor_role, '')) not like ?", ['%system%']);
        }
    }

    /**
     * Normalize audit row into front-end payload format.
     *
     * @return array<string, mixed>
     */
    private function transformAuditLogEntry(object $entry): array
    {
        $actionType = Str::lower(trim((string) ($entry->action_type ?? '')));
        $moduleType = Str::lower(trim((string) ($entry->module_type ?? '')));
        $result = Str::lower(trim((string) ($entry->result ?? 'success')));
        $userType = $this->resolveAuditUserType((string) ($entry->actor_role ?? ''));

        $timestamp = (string) ($entry->created_at ?? '');
        if ($timestamp !== '') {
            try {
                $timestamp = Carbon::parse($timestamp)->format('Y-m-d H:i:s');
            } catch (Throwable $exception) {
                // Keep raw DB timestamp when parsing fails.
            }
        }

        return [
            'id' => (int) ($entry->audit_log_id ?? 0),
            'timestamp' => $timestamp,
            'userName' => (string) ($entry->actor_name ?: 'System'),
            'userRole' => (string) ($entry->actor_role ?: 'System'),
            'userType' => $userType,
            'actionType' => $actionType,
            'actionLabel' => $this->labelizeAuditValue((string) ($entry->action_type ?? 'Unknown')),
            'description' => (string) ($entry->description ?? ''),
            'moduleType' => $moduleType,
            'moduleLabel' => $this->labelizeAuditValue((string) ($entry->module_type ?? 'general')),
            'ipAddress' => (string) ($entry->ip_address ?? '-'),
            'result' => $result,
            'resultLabel' => $this->labelizeAuditValue((string) ($entry->result ?? 'success')),
        ];
    }

    /**
     * Resolve UI user type group used by audit filter.
     */
    private function resolveAuditUserType(string $role): string
    {
        $normalized = Str::lower(trim($role));

        if ($normalized === '') {
            return 'system';
        }

        if (Str::contains($normalized, ['admin', 'staff'])) {
            return 'admin';
        }

        if (Str::contains($normalized, 'donor')) {
            return 'donor';
        }

        if (Str::contains($normalized, 'system')) {
            return 'system';
        }

        return 'other';
    }

    /**
     * Convert machine values (snake or kebab) into readable labels.
     */
    private function labelizeAuditValue(string $value): string
    {
        $normalized = trim(str_replace(['_', '-'], ' ', $value));

        return $normalized === '' ? 'Unknown' : Str::title($normalized);
    }

    /**
     * Map fixed RBAC role id to persisted admins.role value.
     */
    private function roleNameFromRoleId(int $roleId): string
    {
        return $roleId === 1 ? 'admin' : 'staff';
    }

    /**
     * Keep session profile in sync if current admin updates own account.
     */
    private function syncCurrentAdminSessionProfile(Request $request, object $admin): void
    {
        $currentAdminId = (int) $request->session()->get('admin_id');
        if ($currentAdminId !== (int) ($admin->admin_id ?? 0)) {
            return;
        }

        $request->session()->put([
            'admin_username' => (string) ($admin->username ?? ''),
            'admin_full_name' => (string) ($admin->full_name ?? ''),
            'admin_role' => $this->normalizeRole((string) ($admin->role ?? 'staff')),
        ]);
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

        $request->session()->forget([
            'admin_id',
            'admin_username',
            'admin_full_name',
            'admin_role',
            self::TWO_FACTOR_PENDING_SESSION_KEY,
            self::TWO_FACTOR_SETUP_SECRET_SESSION_KEY,
            'two_factor_recovery_codes',
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'You have been logged out.');
    }
}
