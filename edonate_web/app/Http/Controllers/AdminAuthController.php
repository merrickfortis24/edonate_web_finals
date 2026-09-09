<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Mail\AdminPasswordResetMail;
use App\Models\AdminNotificationPreference;
use App\Models\BloodType;
use App\Models\Donor;
use App\Models\DonorAuthentication;
use App\Models\EligibilityStatus;
use App\Models\Location;
use App\Services\AdminNotificationService;
use App\Services\BloodAvailabilityService;
use App\Services\DonationProcessingService;
use App\Services\FacilityBloodInventoryService;
use App\Services\FirebaseGoogleIdentityService;
use App\Services\GeocodingService;
use App\Services\PrivacyLegalSettings;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminAuthController extends BaseController
{
    private const SECURITY_SETTINGS_TABLE = 'admin_security_settings';

    private const ADMIN_NOTIFICATION_PREFERENCES_TABLE = 'admin_notification_preferences';

    private const SYSTEM_SETTINGS_TABLE = 'system_settings';

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
        $loginStats = $this->buildLoginStats();

        if ($this->hasActiveAdminSession($request)) {
            $dashboardRoute = $this->dashboardRouteForRole((string) $request->session()->get('admin_role', ''));
            $twoFactorSetupModal = $this->buildDashboardTwoFactorModalData($request);

            $requiresEnrollment = (bool) ($twoFactorSetupModal['required'] ?? false);
            $hasRecoveryCodes = is_array($twoFactorSetupModal['recoveryCodes'] ?? null)
                && ($twoFactorSetupModal['recoveryCodes'] ?? []) !== [];

            if ($requiresEnrollment || $hasRecoveryCodes) {
                return view('admin.admin_login', [
                    'loginStats' => $loginStats,
                    'twoFactorSetupModal' => $twoFactorSetupModal,
                    'postLoginDashboardUrl' => route($dashboardRoute),
                ]);
            }

            return redirect()->route($dashboardRoute);
        }

        $twoFactorChallengeModal = $this->buildLoginTwoFactorChallengeModalData($request);
        if ((bool) ($twoFactorChallengeModal['show'] ?? false)) {
            return view('admin.admin_login', [
                'loginStats' => $loginStats,
                'twoFactorChallengeModal' => $twoFactorChallengeModal,
            ]);
        }

        return view('admin.admin_login', [
            'loginStats' => $loginStats,
        ]);
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

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            $this->pushFirebaseSecurityEvent('admin_login_password_failed', [
                'email' => Str::lower(trim((string) $validated['email'])),
                'ip' => $request->ip(),
            ]);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        $login = $this->prepareAdminLoginAfterPrimaryAuthentication(
            $request,
            $admin,
            $request->boolean('remember'),
            'password'
        );

        if (! ($login['ok'] ?? false)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => (string) ($login['message'] ?? 'Unable to complete admin sign-in.')]);
        }

        if (($login['status'] ?? '') === 'two_factor') {
            return redirect()
                ->route('admin.login')
                ->with('success', (string) ($login['message'] ?? 'Enter your Google Authenticator code to continue.'));
        }

        if (($login['status'] ?? '') === 'enrollment') {
            return redirect()
                ->route('admin.login')
                ->with('warning', (string) ($login['message'] ?? 'Set up Google Authenticator to continue.'));
        }

        return redirect()->to((string) ($login['redirect_url'] ?? route('admin.login')));
    }

    /**
     * Authenticate an existing admin with a verified Firebase Google identity.
     *
     * Google sign-in is an additional primary sign-in option. Existing local
     * Google Authenticator 2FA remains required for accounts that have it
     * enabled.
     */
    public function googleLogin(Request $request, FirebaseGoogleIdentityService $googleIdentity): JsonResponse
    {
        if (! (bool) config('services.firebase.admin_google_login_enabled', true)) {
            return response()->json([
                'message' => 'Google sign-in is not enabled for the admin portal.',
            ], 404);
        }

        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:12000'],
            'remember' => ['nullable', 'boolean'],
        ]);

        try {
            $identity = $googleIdentity->verify((string) $validated['id_token']);
        } catch (Throwable $exception) {
            $failureType = FirebaseGoogleIdentityService::classifyVerificationFailure($exception);

            // Do not report the exception object: Kreait verification messages
            // can include a prefix of the submitted Firebase ID token.
            Log::warning('Admin Google sign-in token verification failed.', [
                'failure_type' => $failureType,
                'exception_class' => $exception::class,
                'firebase_project' => (string) config('services.firebase.web.project_id', ''),
                'ip' => $request->ip(),
            ]);

            $this->pushFirebaseSecurityEvent('admin_login_google_failed', [
                'reason' => $failureType,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Google sign-in could not be verified. Please try again.',
            ], 401);
        }

        if (($identity['provider'] ?? '') !== 'google.com' || ! ($identity['email_verified'] ?? false)) {
            $failureType = ($identity['provider'] ?? '') !== 'google.com'
                ? 'non_google_firebase_provider'
                : 'unverified_google_email';

            Log::notice('Admin Google sign-in identity rejected.', [
                'failure_type' => $failureType,
                'ip' => $request->ip(),
            ]);

            $this->pushFirebaseSecurityEvent('admin_login_google_failed', [
                'reason' => $failureType,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Please use a verified Google account for admin sign-in.',
            ], 401);
        }

        $email = Str::lower(trim((string) ($identity['email'] ?? '')));
        $admin = DB::table('admins')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $admin) {
            Log::notice('Admin Google sign-in account rejected.', [
                'failure_type' => 'unauthorized_admin_email',
                'ip' => $request->ip(),
            ]);

            $this->pushFirebaseSecurityEvent('admin_login_google_failed', [
                'reason' => 'unauthorized_admin_email',
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'This Google account is not authorized to access the admin portal.',
            ], 403);
        }

        $login = $this->prepareAdminLoginAfterPrimaryAuthentication(
            $request,
            $admin,
            $request->boolean('remember'),
            'google'
        );

        if (! ($login['ok'] ?? false)) {
            Log::notice('Admin Google sign-in account rejected.', [
                'failure_type' => 'unauthorized_admin_role',
                'admin_id' => (int) ($admin->admin_id ?? 0),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => (string) ($login['message'] ?? 'Your account role is not authorized to access this portal.'),
            ], 403);
        }

        if (($login['status'] ?? '') === 'two_factor') {
            return response()->json([
                'message' => (string) ($login['message'] ?? 'Enter your Google Authenticator code to continue.'),
                'requires_two_factor' => true,
                'redirect_url' => route('admin.login'),
            ]);
        }

        if (($login['status'] ?? '') === 'enrollment') {
            return response()->json([
                'message' => (string) ($login['message'] ?? 'Set up Google Authenticator to continue.'),
                'requires_two_factor_setup' => true,
                'redirect_url' => route('admin.login'),
            ]);
        }

        $this->pushFirebaseSecurityEvent('admin_login_google_success', [
            'admin_id' => (int) ($admin->admin_id ?? 0),
            'email' => $email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Google sign-in successful.',
            'redirect_url' => (string) ($login['redirect_url'] ?? route('admin.login')),
        ]);
    }

    /**
     * Apply the existing role, 2FA, enrollment, session, remember-me, and
     * audit rules after any accepted primary authentication method.
     *
     * @return array{ok:bool,status?:string,role?:string,redirect_url?:string,message?:string}
     */
    private function prepareAdminLoginAfterPrimaryAuthentication(
        Request $request,
        object $admin,
        bool $rememberRequested,
        string $primaryMethod = 'password'
    ): array {
        $role = $this->normalizeRole((string) ($admin->role ?? ''));
        if (! $this->isSupportedRole($role)) {
            return [
                'ok' => false,
                'message' => 'Your account role is not authorized to access this portal.',
            ];
        }

        $globalTwoFactorRequired = $this->isGlobalTwoFactorRequired();
        $accountTwoFactorEnabled = $this->isTwoFactorEnabledForAdmin($admin);

        if ($accountTwoFactorEnabled) {
            $this->stagePendingTwoFactorLogin(
                $request,
                $admin,
                $role,
                $rememberRequested,
                $primaryMethod
            );

            $this->pushFirebaseSecurityEvent(
                $primaryMethod === 'google' ? 'admin_login_google_passed' : 'admin_login_password_passed',
                [
                    'admin_id' => (int) ($admin->admin_id ?? 0),
                    'email' => Str::lower(trim((string) ($admin->email ?? ''))),
                    'ip' => $request->ip(),
                ]
            );

            return [
                'ok' => true,
                'status' => 'two_factor',
                'role' => $role,
                'redirect_url' => route('admin.login'),
                'message' => $primaryMethod === 'google'
                    ? 'Google sign-in verified. Enter your Google Authenticator code to continue.'
                    : 'Enter your Google Authenticator code to continue.',
            ];
        }

        $this->clearPendingTwoFactorLogin($request);
        $this->setAdminSession($request, $admin, $role);

        if ($globalTwoFactorRequired && $this->supportsTwoFactorStorage() && ! $accountTwoFactorEnabled) {
            $this->clearRememberMeToken((int) $admin->admin_id);

            $this->pushFirebaseSecurityEvent('admin_2fa_enrollment_required', [
                'admin_id' => (int) ($admin->admin_id ?? 0),
                'email' => Str::lower(trim((string) ($admin->email ?? ''))),
                'ip' => $request->ip(),
            ]);

            return [
                'ok' => true,
                'status' => 'enrollment',
                'role' => $role,
                'redirect_url' => route('admin.login'),
                'message' => 'Set up Google Authenticator to continue.',
            ];
        }

        if ($rememberRequested) {
            $this->issueRememberMeToken((int) $admin->admin_id);
        } else {
            $this->clearRememberMeToken((int) $admin->admin_id);
        }

        return [
            'ok' => true,
            'status' => 'authenticated',
            'role' => $role,
            'redirect_url' => route($this->dashboardRouteForRole($role)),
        ];
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

        if (! $admin || ! $this->isSupportedRole((string) ($admin->role ?? ''))) {
            $this->clearPendingTwoFactorLogin($request);

            return redirect()
                ->route('admin.login')
                ->with('error', 'Your account is no longer available for this login attempt.');
        }

        if (! $this->isTwoFactorEnabledForAdmin($admin)) {
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

        if (! $isValid) {
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

        return $this->completePendingTwoFactorLogin(
            $request,
            $pending,
            $admin,
            $usedRecoveryCode ? 'recovery_code' : 'totp'
        );
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

        if (! $this->isValidPasswordResetToken($validated['email'], $validated['token'])) {
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

        if (! $this->isValidPasswordResetToken($validated['email'], $validated['token'])) {
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
        return view('admin.admin_dashboard', [
            'dashboardPayload' => $this->buildAdminDashboardPayload(),
        ]);
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
        return view('admin.user_management', [
            'userManagementPayload' => [
                'api' => [
                    'listUrl' => route('admin.users.data'),
                    'exportUrl' => route('admin.users.export'),
                    'showUrlTemplate' => route('admin.users.show', ['donor' => '__DONOR_ID__']),
                    'updateUrlTemplate' => route('admin.users.update', ['donor' => '__DONOR_ID__']),
                    'deactivateUrlTemplate' => route('admin.users.deactivate', ['donor' => '__DONOR_ID__']),
                    'csrfToken' => csrf_token(),
                ],
                'filters' => [
                    'bloodTypes' => $this->userManagementBloodTypeOptions(),
                ],
            ],
        ]);
    }

    /**
     * Return paginated donor records for admin user management page.
     */
    public function listUsersData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', Rule::in(['', 'eligible', 'not_eligible'])],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $bloodType = Str::upper(trim((string) ($validated['blood_type'] ?? '')));
        $status = Str::lower(trim((string) ($validated['status'] ?? '')));

        $statusExpression = $this->userManagementStatusExpression();
        $query = $this->userManagementDonorQuery();

        if ($searchTerm !== '') {
            $likeTerm = '%'.$searchTerm.'%';

            $query->where(function ($builder) use ($searchTerm, $likeTerm): void {
                $builder->whereRaw("CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, '')) like ?", [$likeTerm])
                    ->orWhere('da.email', 'like', $likeTerm)
                    ->orWhere('d.contact_number', 'like', $likeTerm);

                if (is_numeric($searchTerm)) {
                    $builder->orWhere('d.donor_id', (int) $searchTerm);
                }
            });
        }

        if ($bloodType !== '') {
            $query->whereRaw("UPPER(COALESCE(bt.blood_type, '')) = ?", [$bloodType]);
        }

        if ($status !== '') {
            $query->whereRaw('('.$statusExpression.') = ?', [$status]);
        }

        $paginator = $query
            ->orderByDesc('d.donor_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $statusCounts = DB::query()
            ->fromSub($this->userManagementDonorQuery(), 'donor_directory')
            ->select('derived_status', DB::raw('COUNT(*) as total'))
            ->groupBy('derived_status')
            ->pluck('total', 'derived_status');

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (object $donor): array => $this->transformUserManagementDonor($donor))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'stats' => [
                'total_donors' => (int) DB::table('donors')->count(),
                'eligible_donors' => (int) ($statusCounts['eligible'] ?? 0),
                'not_eligible_donors' => (int) ($statusCounts['not_eligible'] ?? 0),
                'total_donations' => Schema::hasTable('donation_records')
                    ? (int) DB::table('donation_records')->count()
                    : 0,
            ],
            'filters' => [
                'blood_types' => $this->userManagementBloodTypeOptions(),
                'statuses' => [
                    ['value' => 'eligible', 'label' => 'Eligible'],
                    ['value' => 'not_eligible', 'label' => 'Not Eligible'],
                ],
            ],
        ]);
    }

    /**
     * Export the currently filtered donor directory as a safe CSV download.
     *
     * The export intentionally contains operational donor-directory fields
     * only. Passwords, OTPs, tokens, and identity-document data are never
     * included.
     */
    public function exportUsersCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', Rule::in(['', 'eligible', 'not_eligible'])],
        ]);

        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $bloodType = Str::upper(trim((string) ($validated['blood_type'] ?? '')));
        $status = Str::lower(trim((string) ($validated['status'] ?? '')));
        $statusExpression = $this->userManagementStatusExpression();

        $query = $this->userManagementDonorDetailQuery();

        if ($searchTerm !== '') {
            $likeTerm = '%'.$searchTerm.'%';

            $query->where(function ($builder) use ($searchTerm, $likeTerm): void {
                $builder->whereRaw("CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, '')) like ?", [$likeTerm])
                    ->orWhere('da.email', 'like', $likeTerm)
                    ->orWhere('d.contact_number', 'like', $likeTerm);

                if (is_numeric($searchTerm)) {
                    $builder->orWhere('d.donor_id', (int) $searchTerm);
                }
            });
        }

        if ($bloodType !== '') {
            $query->whereRaw("UPPER(COALESCE(bt.blood_type, '')) = ?", [$bloodType]);
        }

        if ($status !== '') {
            $query->whereRaw('('.$statusExpression.') = ?', [$status]);
        }

        $rows = $query
            ->orderByDesc('d.donor_id')
            ->cursor();

        $fileName = 'donors-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Donor ID',
                'Donor Code',
                'Full Name',
                'Email',
                'Contact Number',
                'Blood Type',
                'Blood Type Status',
                'Identity Verification',
                'Eligibility',
                'Last Donation',
                'Total Donations',
                'Account Status',
            ]);

            foreach ($rows as $row) {
                $donor = $this->transformUserManagementDonorDetail($row);
                fputcsv($handle, [
                    $this->safeCsvCell($donor['donor_id'] ?? ''),
                    $this->safeCsvCell($donor['donor_code'] ?? ''),
                    $this->safeCsvCell($donor['full_name'] ?? ''),
                    $this->safeCsvCell($donor['email'] ?? ''),
                    $this->safeCsvCell($donor['contact_number'] ?? ''),
                    $this->safeCsvCell($donor['blood_type'] ?? 'Not Yet Determined'),
                    $this->safeCsvCell($donor['blood_type_status'] ?? 'not_yet_determined'),
                    $this->safeCsvCell($donor['verification_status'] ?? 'unverified'),
                    $this->safeCsvCell($donor['eligibility_status'] ?? 'eligible'),
                    $this->safeCsvCell($donor['last_donation_date'] ?? ''),
                    $this->safeCsvCell($donor['total_donations'] ?? 0),
                    $this->safeCsvCell(($donor['is_active'] ?? true) ? 'active' : 'inactive'),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Return a single donor record for the admin user management page.
     */
    public function showUser(Request $request, int $donor): JsonResponse
    {
        $donorPayload = $this->getUserManagementDonorDetail($donor);
        if ($donorPayload === null) {
            return response()->json([
                'message' => 'Donor not found.',
            ], 404);
        }

        return response()->json([
            'donor' => $donorPayload,
            'options' => [
                'blood_types' => $this->userManagementBloodTypeFormOptions(),
                'statuses' => $this->userManagementStatusOptions(),
            ],
        ]);
    }

    /**
     * Update a donor record from the admin user management page.
     */
    public function updateUser(Request $request, int $donor): JsonResponse
    {
        $targetDonor = Donor::query()->find($donor);
        if (! $targetDonor) {
            return response()->json([
                'message' => 'Donor not found.',
            ], 404);
        }

        $targetAuth = DonorAuthentication::query()
            ->where('donor_id', $targetDonor->donor_id)
            ->orderByDesc('auth_id')
            ->first();

        if (! $targetAuth) {
            return response()->json([
                'message' => 'This donor cannot be updated because the authentication record is missing.',
            ], 422);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('donor_authentication', 'email')->ignore($targetAuth->auth_id, 'auth_id')],
            'contact_number' => ['nullable', 'regex:/^(\+63|0)\d{10}$/'],
            'gender' => ['nullable', 'string', Rule::in(['Male', 'Female', 'Other', 'Prefer not to say'])],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'blood_type_id' => ['nullable', 'integer', Rule::exists('blood_types', 'blood_type_id')],
            'street_address' => ['nullable', 'string', 'max:150'],
            'barangay_name' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'eligibility_status' => ['nullable', 'string', Rule::in(['eligible', 'not_eligible'])],
        ]);

        $requestedBloodTypeId = isset($validated['blood_type_id']) ? (int) $validated['blood_type_id'] : null;
        $currentBloodTypeStatus = Str::lower(trim((string) ($targetDonor->blood_type_status ?? 'not_yet_determined')));
        if ($currentBloodTypeStatus === 'verified' && $requestedBloodTypeId !== (int) ($targetDonor->blood_type_id ?? 0)) {
            return response()->json([
                'message' => 'A verified blood type can only be corrected during an authorized completed donation review.',
            ], 422);
        }

        $manualCoordinates = $this->userManagementManualCoordinates($validated);
        if ($manualCoordinates === false) {
            return response()->json([
                'message' => 'Please provide both latitude and longitude, or leave both blank for automatic geocoding.',
            ], 422);
        }

        $before = $this->getUserManagementDonorDetail($targetDonor->donor_id) ?? [];
        $currentLocation = $targetDonor->location_id
            ? Location::query()->find($targetDonor->location_id)
            : null;
        $latestEligibility = EligibilityStatus::query()
            ->where('donor_id', $targetDonor->donor_id)
            ->orderByDesc('eligibility_id')
            ->first();

        $locationToGeocodeId = null;

        DB::transaction(function () use ($validated, $targetDonor, $targetAuth, $currentLocation, $latestEligibility, $before, $manualCoordinates, &$locationToGeocodeId): void {
            $locationPayload = $this->userManagementLocationPayload($validated);
            $hasLocationData = $this->userManagementLocationPayloadHasValue($locationPayload);
            $locationIsShared = $currentLocation
                ? Donor::query()
                    ->where('location_id', $currentLocation->location_id)
                    ->where('donor_id', '!=', $targetDonor->donor_id)
                    ->exists()
                : false;

            $nextLocationId = $targetDonor->location_id;
            $locationToDelete = null;
            $locationAddressChanged = $currentLocation
                ? ! $this->userManagementLocationMatches($currentLocation, $locationPayload)
                : true;
            $coordinatePayload = is_array($manualCoordinates)
                ? $manualCoordinates
                : ($locationAddressChanged ? ['latitude' => null, 'longitude' => null] : []);

            if (! $hasLocationData) {
                $nextLocationId = null;

                if ($currentLocation && ! $locationIsShared) {
                    $locationToDelete = $currentLocation;
                }
            } elseif ($currentLocation && ! $locationIsShared) {
                $currentLocation->fill(array_merge($locationPayload, $coordinatePayload));
                $currentLocation->save();
                $nextLocationId = (int) $currentLocation->location_id;
            } elseif ($currentLocation && $manualCoordinates === null && $this->userManagementLocationMatches($currentLocation, $locationPayload)) {
                $nextLocationId = (int) $currentLocation->location_id;
            } else {
                $newLocation = Location::query()->create(array_merge($locationPayload, is_array($manualCoordinates)
                    ? $manualCoordinates
                    : ['latitude' => null, 'longitude' => null]));
                $nextLocationId = (int) $newLocation->location_id;
            }

            if ($hasLocationData && $manualCoordinates === null && $nextLocationId !== null) {
                $nextLocation = $nextLocationId === (int) ($currentLocation->location_id ?? 0)
                    ? $currentLocation
                    : Location::query()->find($nextLocationId);

                if (! $nextLocation || ! app(GeocodingService::class)->hasCoordinates($nextLocation)) {
                    $locationToGeocodeId = $nextLocationId;
                }
            }

            $targetDonor->fill([
                'first_name' => trim((string) $validated['first_name']),
                'last_name' => trim((string) $validated['last_name']),
                'gender' => $this->userManagementNullableString($validated['gender'] ?? null),
                'birthdate' => $validated['birthdate'] ?? null,
                'contact_number' => $this->userManagementNullableString($validated['contact_number'] ?? null),
                'blood_type_id' => isset($validated['blood_type_id']) ? (int) $validated['blood_type_id'] : null,
                'location_id' => $nextLocationId,
            ]);

            if (Schema::hasColumn('donors', 'blood_type_status')
                && strtolower(trim((string) ($targetDonor->blood_type_status ?? 'not_yet_determined'))) !== 'verified') {
                $targetDonor->blood_type_status = $targetDonor->blood_type_id ? 'self_reported' : 'not_yet_determined';
                $targetDonor->blood_type_verified_by_admin_id = null;
                $targetDonor->blood_type_verified_at = null;
            }
            $targetDonor->save();

            $targetAuth->email = Str::lower(trim((string) $validated['email']));
            $targetAuth->save();

            if ($locationToDelete instanceof Location) {
                $locationToDelete->delete();
            }

            $requestedStatus = $this->userManagementNullableString($validated['eligibility_status'] ?? null);
            if ($requestedStatus !== null) {
                $normalizedRequestedStatus = $this->normalizeUserManagementStatusValue($requestedStatus);
                $currentDerivedStatus = $this->normalizeUserManagementStatusValue((string) ($before['eligibility_status'] ?? 'eligible'));

                if ($latestEligibility instanceof EligibilityStatus) {
                    $latestEligibility->status = $this->userManagementStatusDatabaseValue($normalizedRequestedStatus);
                    if (Schema::hasColumn('eligibility_status', 'source')) {
                        $latestEligibility->source = 'admin_review';
                    }
                    if (Schema::hasColumn('eligibility_status', 'result_reason')) {
                        $latestEligibility->result_reason = 'Updated by admin from user management.';
                    }
                    $latestEligibility->save();
                } elseif ($normalizedRequestedStatus !== $currentDerivedStatus) {
                    $eligibilityPayload = [
                        'donor_id' => $targetDonor->donor_id,
                        'status' => $this->userManagementStatusDatabaseValue($normalizedRequestedStatus),
                    ];
                    if (Schema::hasColumn('eligibility_status', 'source')) {
                        $eligibilityPayload['source'] = 'admin_review';
                    }
                    if (Schema::hasColumn('eligibility_status', 'result_reason')) {
                        $eligibilityPayload['result_reason'] = 'Updated by admin from user management.';
                    }

                    EligibilityStatus::query()->create($eligibilityPayload);
                }
            }
        });

        if ($locationToGeocodeId !== null) {
            $locationToGeocode = Location::query()->find($locationToGeocodeId);
            if ($locationToGeocode) {
                app(GeocodingService::class)->geocodeAndSave($locationToGeocode);
            }
        }

        $after = $this->getUserManagementDonorDetail($targetDonor->donor_id);

        $this->logUserManagementAudit(
            $request,
            'update',
            'Updated donor account: '.($after['full_name'] ?? ('Donor #'.$targetDonor->donor_id)),
            (int) $targetDonor->donor_id,
            [
                'changes' => $this->userManagementChangedFields($before, $after ?? []),
            ]
        );

        return response()->json([
            'message' => 'Donor updated successfully.',
            'donor' => $after,
        ]);
    }

    /**
     * Deactivate a donor account without removing its historical records.
     */
    public function deactivateUser(Request $request, int $donor): JsonResponse
    {
        $targetDonor = Donor::query()->find($donor);
        if (! $targetDonor) {
            return response()->json([
                'message' => 'Donor not found.',
            ], 404);
        }

        if (! Schema::hasColumn('donors', 'is_active')) {
            return response()->json([
                'message' => 'Donor deactivation is unavailable until the account-status migration has been applied.',
            ], 422);
        }

        $targetSummary = $this->getUserManagementDonorDetail($targetDonor->donor_id) ?? [];
        if (! (bool) ($targetDonor->is_active ?? true)) {
            return response()->json([
                'message' => 'This donor account is already inactive.',
                'donor' => $targetSummary,
            ]);
        }

        DB::transaction(function () use ($targetDonor): void {
            $targetDonor->forceFill(['is_active' => false])->save();
        });

        $this->logUserManagementAudit(
            $request,
            'deactivate',
            'Deactivated donor account: '.($targetSummary['full_name'] ?? ('Donor #'.$targetDonor->donor_id)),
            (int) $targetDonor->donor_id,
            [
                'email' => $targetSummary['email'] ?? null,
                'donor_code' => $targetSummary['donor_code'] ?? null,
                'historical_records_preserved' => true,
            ]
        );

        return response()->json([
            'message' => 'Donor account deactivated. Historical records were preserved.',
            'donor' => $this->getUserManagementDonorDetail($targetDonor->donor_id),
        ]);
    }

    /**
     * Display appointment management page.
     */
    public function appointments(Request $request)
    {
        return view('admin.appointment_management', [
            'appointmentManagementPayload' => [
                'api' => [
                    'listUrl' => route('admin.appointments.data'),
                    'donationProcessingUrl' => route('admin.donation-records'),
                ],
                'filters' => [
                    'centers' => $this->appointmentManagementCenterOptions(),
                ],
            ],
        ]);
    }

    /**
     * Return paginated appointment list for admin appointment management page.
     */
    public function listAppointmentsData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'center' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', Rule::in(['', 'confirmed', 'pending', 'cancelled', 'rescheduled', 'checked_in', 'completed', 'deferred_on_site', 'no_show'])],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $center = trim((string) ($validated['center'] ?? ''));
        $status = Str::lower(trim((string) ($validated['status'] ?? '')));

        $statusExpression = $this->appointmentStatusExpression();
        $centerExpression = $this->appointmentCenterExpression();

        $query = $this->appointmentManagementBaseQuery();

        if ($searchTerm !== '') {
            $likeTerm = '%'.$searchTerm.'%';
            $numericSearch = null;

            if (preg_match('/(\d+)/', $searchTerm, $matches) === 1) {
                $numericSearch = (int) ($matches[1] ?? 0);
            }

            $query->where(function ($builder) use ($likeTerm, $numericSearch): void {
                $builder->whereRaw("CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, '')) like ?", [$likeTerm])
                    ->orWhere('da.email', 'like', $likeTerm)
                    ->orWhere('ap.status', 'like', $likeTerm);

                if ($numericSearch !== null && $numericSearch > 0) {
                    $builder->orWhere('ap.appointment_id', $numericSearch)
                        ->orWhere('ap.donor_id', $numericSearch);
                }
            });
        }

        if ($center !== '') {
            $query->whereRaw('LOWER('.$centerExpression.') = ?', [Str::lower($center)]);
        }

        if ($status !== '') {
            $query->whereRaw('('.$statusExpression.') = ?', [$status]);
        }

        $paginator = $query
            ->orderByDesc('ap.appointment_date')
            ->orderByDesc('ap.appointment_time')
            ->orderByDesc('ap.appointment_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $statusCounts = DB::query()
            ->fromSub($this->appointmentManagementBaseQuery(), 'appointment_directory')
            ->select('normalized_status', DB::raw('COUNT(*) as total'))
            ->groupBy('normalized_status')
            ->pluck('total', 'normalized_status');

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (object $entry): array => $this->transformAppointmentManagementRow($entry))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'stats' => [
                'confirmed' => (int) ($statusCounts['confirmed'] ?? 0),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
                'rescheduled' => (int) ($statusCounts['rescheduled'] ?? 0),
                'checked_in' => (int) ($statusCounts['checked_in'] ?? 0),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
                'deferred_on_site' => (int) ($statusCounts['deferred_on_site'] ?? 0),
                'no_show' => (int) ($statusCounts['no_show'] ?? 0),
            ],
            'filters' => [
                'centers' => $this->appointmentManagementCenterOptions(),
            ],
        ]);
    }

    /**
     * Approve (confirm) a pending appointment.
     */
    public function approveAppointment(Request $request, int $appointment): JsonResponse
    {
        $row = DB::table('appointments')->where('appointment_id', $appointment)->first();
        if (! $row) {
            return response()->json(['message' => 'Appointment not found.'], 404);
        }

        $normalized = $this->normalizeAppointmentStatusValue((string) ($row->status ?? ''));
        if ($normalized !== 'pending') {
            return response()->json(['message' => 'Only pending appointments can be approved.'], 422);
        }

        $actorAdminId = is_numeric($request->session()->get('admin_id'))
            ? (int) $request->session()->get('admin_id')
            : null;

        DB::table('appointments')->where('appointment_id', $appointment)->update([
            'status' => 'confirmed',
            'admin_id' => $actorAdminId,
        ]);

        $donorId = is_numeric($row->donor_id) ? (int) $row->donor_id : null;
        $appointmentCode = 'AP'.str_pad((string) $appointment, 3, '0', STR_PAD_LEFT);

        $this->createDonorNotification($donorId, 'appointment_approved', "Your appointment {$appointmentCode} has been approved.");
        app(AdminNotificationService::class)->createAdminEvent(
            'appointment_approved',
            'Appointment Approved',
            "Appointment {$appointmentCode} has been approved.",
            'appointment',
            $appointment
        );
        $this->logAppointmentAudit($request, 'appointment_approved', "Approved appointment {$appointmentCode}.", $appointment, [
            'appointment_id' => $appointment,
            'donor_id' => $donorId,
            'previous_status' => $row->status ?? null,
            'new_status' => 'confirmed',
        ]);

        return response()->json(['message' => 'Appointment approved.']);
    }

    /**
     * Reject (cancel) a pending appointment.
     */
    public function rejectAppointment(Request $request, int $appointment): JsonResponse
    {
        $row = DB::table('appointments')->where('appointment_id', $appointment)->first();
        if (! $row) {
            return response()->json(['message' => 'Appointment not found.'], 404);
        }

        $normalized = $this->normalizeAppointmentStatusValue((string) ($row->status ?? ''));
        if ($normalized !== 'pending') {
            return response()->json(['message' => 'Only pending appointments can be rejected.'], 422);
        }

        $actorAdminId = is_numeric($request->session()->get('admin_id'))
            ? (int) $request->session()->get('admin_id')
            : null;

        DB::table('appointments')->where('appointment_id', $appointment)->update([
            'status' => 'cancelled',
            'admin_id' => $actorAdminId,
        ]);

        $donorId = is_numeric($row->donor_id) ? (int) $row->donor_id : null;
        $appointmentCode = 'AP'.str_pad((string) $appointment, 3, '0', STR_PAD_LEFT);

        $this->createDonorNotification($donorId, 'appointment_rejected', "Your appointment {$appointmentCode} has been rejected.");
        app(AdminNotificationService::class)->createAdminEvent(
            'appointment_cancelled',
            'Appointment Cancellation',
            "Appointment {$appointmentCode} has been cancelled.",
            'appointment',
            $appointment
        );
        $this->logAppointmentAudit($request, 'appointment_rejected', "Rejected appointment {$appointmentCode}.", $appointment, [
            'appointment_id' => $appointment,
            'donor_id' => $donorId,
            'previous_status' => $row->status ?? null,
            'new_status' => 'cancelled',
        ]);

        return response()->json(['message' => 'Appointment rejected.']);
    }

    public function checkInAppointment(Request $request, int $appointment, DonationProcessingService $service): JsonResponse
    {
        $result = $service->checkIn($appointment, $this->currentAdminId($request), $request);

        return response()->json([
            'message' => $result['already'] ?? false
                ? 'This appointment has already been checked in.'
                : 'Appointment checked in.',
        ]);
    }

    /**
     * Display the protected completion workspace opened from Donation
     * Processing. The page is intentionally limited to checked-in
     * appointments and reuses the donor detail query used by Digital Donor ID.
     */
    public function completeDonationPage(Request $request, int $appointment)
    {
        $entry = $this->donationProcessingBaseQuery()
            ->where('ap.appointment_id', $appointment)
            ->first();

        if (! $entry) {
            abort(404, 'Appointment not found.');
        }

        $status = Str::lower(trim((string) ($entry->normalized_status ?? '')));
        if ($status !== 'checked_in') {
            abort(422, 'Only checked-in appointments can be completed.');
        }

        $donor = $this->getUserManagementDonorDetail((int) $entry->donor_id);
        if ($donor === null) {
            abort(404, 'Donor not found.');
        }

        return view('admin.complete_donation', [
            'completionPayload' => [
                'appointment' => [
                    'appointment_id' => (int) $entry->appointment_id,
                    'appointment_code' => 'AP'.str_pad((string) ((int) $entry->appointment_id), 3, '0', STR_PAD_LEFT),
                    'donor_id' => (int) $entry->donor_id,
                    'appointment_date' => ! empty($entry->appointment_date) ? (string) $entry->appointment_date : null,
                    'appointment_time' => ! empty($entry->appointment_time) ? (string) $entry->appointment_time : null,
                    'event_title' => trim((string) ($entry->event_title ?? 'Legacy appointment')),
                    'center_label' => trim((string) ($entry->donation_center ?: $entry->event_location_name ?: 'N/A')),
                ],
                'donor' => $donor,
                'canVerifyBloodType' => Str::lower(trim((string) $request->session()->get('admin_role'))) === 'admin',
                'verificationBloodTypes' => $this->userManagementBloodTypeFormOptions(),
                'api' => [
                    'completeUrl' => route('admin.appointments.complete', ['appointment' => $appointment]),
                    'returnUrl' => route('admin.donation-records'),
                ],
            ],
        ]);
    }

    /**
     * Complete a checked-in appointment and create exactly one donation record.
     */
    public function completeAppointment(Request $request, int $appointment, DonationProcessingService $service): JsonResponse
    {
        $validated = $request->validate([
            'donation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'blood_units' => ['required', 'integer', 'min:1', 'max:10'],
            'verified_blood_type_id' => ['nullable', 'integer', 'exists:blood_types,blood_type_id'],
            'confirm_blood_type_change' => ['nullable', 'boolean'],
            'blood_type_change_reason' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $service->completeDonation($appointment, $this->currentAdminId($request), $validated, $request);
        // The completion response is also used to refresh the Digital ID
        // window. Some focused test schemas (and older installations) do not
        // have the optional locations table, so keep the canonical donation
        // response independent from that optional detail join.
        $donorPayload = Schema::hasTable('locations')
            ? $this->getUserManagementDonorDetail((int) $result['appointment']->donor_id)
            : null;

        return response()->json([
            'message' => $result['already'] ?? false
                ? 'This appointment already has a completed donation record.'
                : 'Donation completed and record created.',
            'donation_id' => $result['record']->donation_id ?? null,
            'donor' => $donorPayload,
        ]);
    }

    public function deferAppointmentOnSite(Request $request, int $appointment, DonationProcessingService $service): JsonResponse
    {
        $validated = $request->validate([
            'deferred_reason' => ['required', 'string', 'min:8', 'max:1000'],
            'next_eligible_date' => ['nullable', 'date', 'after_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $service->deferOnSite($appointment, $this->currentAdminId($request), $validated, $request);

        return response()->json([
            'message' => $result['already'] ?? false
                ? 'This appointment already has a deferred donation record.'
                : 'Donation deferred on site.',
            'donation_id' => $result['record']->donation_id ?? null,
        ]);
    }

    public function cancelAppointment(Request $request, int $appointment): JsonResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $row = DB::table('appointments')->where('appointment_id', $appointment)->first();
        if (! $row) {
            return response()->json(['message' => 'Appointment not found.'], 404);
        }

        $normalized = $this->normalizeAppointmentStatusValue((string) ($row->status ?? ''));
        if (in_array($normalized, ['cancelled', 'completed', 'checked_in', 'deferred_on_site', 'no_show'], true)) {
            return response()->json(['message' => 'This appointment can no longer be cancelled.'], 422);
        }

        $actorAdminId = is_numeric($request->session()->get('admin_id'))
            ? (int) $request->session()->get('admin_id')
            : null;

        DB::table('appointments')->where('appointment_id', $appointment)->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'] ?? null,
            'admin_id' => $actorAdminId,
        ]);

        $donorId = is_numeric($row->donor_id) ? (int) $row->donor_id : null;
        $appointmentCode = 'AP'.str_pad((string) $appointment, 3, '0', STR_PAD_LEFT);

        $this->createDonorNotification($donorId, 'appointment_cancelled', "Your appointment {$appointmentCode} has been cancelled.");
        app(AdminNotificationService::class)->createAdminEvent(
            'appointment_cancelled',
            'Appointment Cancellation',
            "Appointment {$appointmentCode} has been cancelled.",
            'appointment',
            $appointment
        );
        $this->logAppointmentAudit($request, 'appointment_cancelled', "Cancelled appointment {$appointmentCode}.", $appointment, [
            'appointment_id' => $appointment,
            'donor_id' => $donorId,
            'previous_status' => $row->status ?? null,
            'new_status' => 'cancelled',
            'reason' => $validated['cancellation_reason'] ?? null,
        ]);

        return response()->json(['message' => 'Appointment cancelled.']);
    }

    public function markNoShowAppointment(Request $request, int $appointment, DonationProcessingService $service): JsonResponse
    {
        $result = $service->markNoShow($appointment, $this->currentAdminId($request), $request);

        return response()->json([
            'message' => $result['already'] ?? false
                ? 'This appointment was already marked as no-show.'
                : 'Appointment marked as no-show.',
        ]);
    }

    /**
     * Display donation records page.
     */
    public function donationRecords(Request $request)
    {
        return view('admin.donor_records', [
            'donationRecordsPayload' => [
                'canVerifyBloodType' => Str::lower(trim((string) $request->session()->get('admin_role'))) === 'admin',
                'api' => [
                    'listUrl' => route('admin.donation-records.data'),
                    'completeUrlTemplate' => route('admin.appointments.complete', ['appointment' => '__ID__']),
                    'completePageUrlTemplate' => route('admin.appointments.complete-page', ['appointment' => '__ID__']),
                    'returnUrl' => route('admin.donation-records'),
                    'deferUrlTemplate' => route('admin.appointments.defer', ['appointment' => '__ID__']),
                    'initialAppointmentId' => $request->integer('appointment_id') ?: null,
                ],
                'filters' => [
                    'bloodTypes' => $this->userManagementBloodTypeOptions(),
                    'verificationBloodTypes' => $this->userManagementBloodTypeFormOptions(),
                    'events' => $this->donationProcessingEventOptions(),
                    'centers' => $this->donationProcessingCenterOptions(),
                ],
            ],
        ]);
    }

    /**
     * Return paginated rows for the Donation Processing page.
     */
    public function listDonationRecordsData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'event_id' => ['nullable', 'integer', 'min:1'],
            'center' => ['nullable', 'string', 'max:150'],
            'date' => ['nullable', 'date'],
            'appointment_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(['', 'confirmed', 'checked_in', 'completed', 'deferred_on_site', 'no_show', 'cancelled'])],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $bloodType = trim((string) ($validated['blood_type'] ?? ''));
        $eventId = isset($validated['event_id']) ? (int) $validated['event_id'] : null;
        $center = trim((string) ($validated['center'] ?? ''));
        $date = trim((string) ($validated['date'] ?? ''));
        $appointmentId = isset($validated['appointment_id']) ? (int) $validated['appointment_id'] : null;
        $status = Str::lower(trim((string) ($validated['status'] ?? '')));

        $query = $this->donationProcessingBaseQuery();
        $statusExpression = $this->appointmentStatusExpression('ap');

        if ($searchTerm !== '') {
            $likeTerm = '%'.$searchTerm.'%';
            $numericSearch = null;

            if (preg_match('/(\d+)/', $searchTerm, $matches) === 1) {
                $numericSearch = (int) ($matches[1] ?? 0);
            }

            $query->where(function ($builder) use ($likeTerm, $numericSearch): void {
                $builder->whereRaw("CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, '')) like ?", [$likeTerm])
                    ->orWhere('da.email', 'like', $likeTerm)
                    ->orWhere('de.title', 'like', $likeTerm)
                    ->orWhere('ap.donation_center', 'like', $likeTerm)
                    ->orWhere('dr.remarks', 'like', $likeTerm)
                    ->orWhere('dr.deferred_reason', 'like', $likeTerm);

                if ($numericSearch !== null && $numericSearch > 0) {
                    $builder->orWhere('ap.appointment_id', $numericSearch)
                        ->orWhere('ap.donor_id', $numericSearch)
                        ->orWhere('ap.event_id', $numericSearch)
                        ->orWhere('dr.donation_id', $numericSearch);
                }
            });
        }

        if ($bloodType !== '') {
            $query->whereRaw("LOWER(COALESCE(bt.blood_type, '')) = ?", [Str::lower($bloodType)]);
        }

        if ($eventId !== null && $eventId > 0) {
            $query->where('ap.event_id', $eventId);
        }

        if ($center !== '') {
            $query->whereRaw("LOWER(COALESCE(NULLIF(ap.donation_center, ''), NULLIF(de.location_name, ''), '')) = ?", [Str::lower($center)]);
        }

        if ($date !== '') {
            $query->whereDate('ap.appointment_date', Carbon::parse($date)->toDateString());
        }

        if ($appointmentId !== null && $appointmentId > 0) {
            $query->where('ap.appointment_id', $appointmentId);
        }

        if ($status !== '') {
            $query->whereRaw('('.$statusExpression.') = ?', [$status]);
        }

        $paginator = $query
            ->orderByDesc('ap.appointment_date')
            ->orderByDesc('ap.appointment_time')
            ->orderByDesc('ap.appointment_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $statusCounts = DB::query()
            ->fromSub($this->donationProcessingBaseQuery(), 'donation_processing')
            ->select('normalized_status', DB::raw('COUNT(*) as total'))
            ->groupBy('normalized_status')
            ->pluck('total', 'normalized_status');

        $today = Carbon::today()->toDateString();
        $todayExpected = (int) DB::query()
            ->fromSub($this->donationProcessingBaseQuery(), 'donation_processing')
            ->where('appointment_date', $today)
            ->where('normalized_status', 'confirmed')
            ->count();

        $checkedInToday = (int) DB::query()
            ->fromSub($this->donationProcessingBaseQuery(), 'donation_processing')
            ->whereDate('checked_in_at', $today)
            ->where('normalized_status', 'checked_in')
            ->count();

        $completedThisMonth = (int) DB::table('donation_records')
            ->whereDate('donation_date', '>=', Carbon::now()->startOfMonth()->toDateString())
            ->whereDate('donation_date', '<=', Carbon::now()->endOfMonth()->toDateString())
            ->whereRaw("LOWER(COALESCE(donation_status, 'completed')) = ?", ['completed'])
            ->count();

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (object $entry): array => $this->transformDonationProcessingRow($entry))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'stats' => [
                'expected_today' => $todayExpected,
                'checked_in_today' => $checkedInToday,
                'completed_month' => $completedThisMonth,
                'confirmed' => (int) ($statusCounts['confirmed'] ?? 0),
                'checked_in' => (int) ($statusCounts['checked_in'] ?? 0),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
                'deferred_on_site' => (int) ($statusCounts['deferred_on_site'] ?? 0),
                'no_show' => (int) ($statusCounts['no_show'] ?? 0),
            ],
            'filters' => [
                'blood_types' => $this->userManagementBloodTypeOptions(),
                'events' => $this->donationProcessingEventOptions(),
                'centers' => $this->donationProcessingCenterOptions(),
            ],
        ]);
    }

    /**
     * Display blood availability mapping page.
     */
    public function bloodAvailabilityMapping(
        Request $request,
        BloodAvailabilityService $availability,
        FacilityBloodInventoryService $inventory
    ) {
        return view('admin.blood_availability_mapping', [
            'bloodTypes' => $availability->bloodTypeNames(),
            'facilityTypes' => $inventory->facilityTypes(),
        ]);
    }

    /**
     * GET /admin/blood-availability/map-data
     * Returns aggregate donor availability only. No donor identity or address data is included.
     */
    public function mapData(Request $request, BloodAvailabilityService $availability): JsonResponse
    {
        return response()->json($availability->getMapData($this->bloodAvailabilityFilters($request, $availability)));
    }

    /**
     * Legacy-compatible aggregate map endpoint.
     * It deliberately returns barangay aggregates, never individual donors.
     */
    public function mapDonors(Request $request, BloodAvailabilityService $availability): JsonResponse
    {
        $data = $availability->getMapData($this->bloodAvailabilityFilters($request, $availability));

        return response()->json($data['map_points']);
    }

    /**
     * POST /admin/map/geocode-missing
     * Geocode a small batch of missing location coordinates from the admin map.
     */
    public function geocodeMissingLocations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $limit = (int) ($validated['limit'] ?? 5);
        $locations = Location::query()
            ->where(function ($query): void {
                $query->whereNull('latitude')
                    ->orWhereNull('longitude');
            })
            ->orderBy('location_id')
            ->limit($limit)
            ->get();

        if ($locations->isEmpty()) {
            return response()->json([
                'message' => 'No missing location coordinates found.',
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
            ]);
        }

        $geocoding = app(GeocodingService::class);
        $succeeded = 0;
        $failed = 0;

        foreach ($locations as $index => $location) {
            if ($geocoding->geocodeAndSave($location)) {
                $succeeded++;
            } else {
                $failed++;
            }

            if ($index < $locations->count() - 1) {
                sleep(1);
            }
        }

        return response()->json([
            'message' => "Geocoding finished. {$succeeded} updated, {$failed} failed.",
            'processed' => $locations->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ]);
    }

    /**
     * Legacy-compatible per-barangay aggregate endpoint.
     */
    public function mapBarangays(Request $request, BloodAvailabilityService $availability): JsonResponse
    {
        $data = $availability->getMapData($this->bloodAvailabilityFilters($request, $availability));

        return response()->json($data['barangays']);
    }

    /**
     * Legacy-compatible aggregate summary endpoint.
     */
    public function mapSummary(Request $request, BloodAvailabilityService $availability): JsonResponse
    {
        $data = $availability->getMapData($this->bloodAvailabilityFilters($request, $availability));

        return response()->json([
            'total_donors' => $data['summary']['available_donors'],
            'mapped_locations' => $data['data_quality']['mapped_available_donors'],
            'unmapped_donors' => $data['data_quality']['available_donors_missing_coordinates'],
            'blood_type_breakdown' => collect($data['blood_types'])->map(
                static fn (int $count, string $bloodType): array => ['blood_type' => $bloodType, 'count' => $count]
            )->values(),
            'last_updated' => $data['last_updated'],
            'summary' => $data['summary'],
            'data_quality' => $data['data_quality'],
        ]);
    }

    /** @return array{blood_type: string|null, barangay: string|null, city: string|null} */
    private function bloodAvailabilityFilters(Request $request, BloodAvailabilityService $availability): array
    {
        $validated = $request->validate([
            'blood_type' => ['nullable', 'string', 'max:10', Rule::in($availability->bloodTypeNames())],
            'barangay' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:150'],
        ]);

        return [
            'blood_type' => trim((string) ($validated['blood_type'] ?? '')) ?: null,
            'barangay' => trim((string) ($validated['barangay'] ?? '')) ?: null,
            'city' => trim((string) ($validated['city'] ?? '')) ?: null,
        ];
    }

    /**
     * Classify a donor count into an availability level string.
     */
    private function classifyAvailability(int $count): string
    {
        return match (true) {
            $count >= 10 => 'high',
            $count >= 5 => 'medium',
            $count >= 2 => 'low',
            default => 'critical',
        };
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
                'detailUrlTemplate' => url('/admin/audit-logs/__AUDIT_ID__'),
            ],
        ]);
    }

    /**
     * Return one append-only audit entry with redacted metadata for the detail modal.
     */
    public function showAuditLog(int $auditLog): JsonResponse
    {
        if (! Schema::hasTable('audit_logs')) {
            return response()->json(['message' => 'Audit log storage is not available.'], 404);
        }

        $entry = DB::table('audit_logs')->where('audit_log_id', $auditLog)->first();
        if (! $entry) {
            return response()->json(['message' => 'Audit log entry not found.'], 404);
        }

        return response()->json([
            'data' => $this->transformAuditLogEntry($entry, true),
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
            'actor' => ['nullable', 'string', 'max:150'],
            'actor_role' => ['nullable', 'string', 'max:50'],
            'action_type' => ['nullable', 'string', 'max:50'],
            'module_type' => ['nullable', 'string', 'max:80'],
            'result' => ['nullable', 'string', Rule::in(['', 'success', 'failed', 'warning'])],
            'user_type' => ['nullable', 'string', Rule::in(['', 'admin', 'donor', 'system', 'other'])],
            'security_policy_only' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $actor = trim((string) ($validated['actor'] ?? ''));
        $actorRole = Str::lower(trim((string) ($validated['actor_role'] ?? '')));
        $actionType = Str::lower(trim((string) ($validated['action_type'] ?? '')));
        $moduleType = Str::lower(trim((string) ($validated['module_type'] ?? '')));
        $result = Str::lower(trim((string) ($validated['result'] ?? '')));
        $userType = Str::lower(trim((string) ($validated['user_type'] ?? '')));
        $securityPolicyOnly = (bool) ($validated['security_policy_only'] ?? false);
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $baseQuery = DB::table('audit_logs');
        $this->applyAuditLogFilters($baseQuery, $searchTerm, $actor, $actorRole, $actionType, $moduleType, $result, $userType, $securityPolicyOnly, $startDate, $endDate);

        $statsRows = (clone $baseQuery)
            ->selectRaw("LOWER(COALESCE(result, 'success')) as result_key, COUNT(*) as total")
            ->groupBy('result_key')
            ->pluck('total', 'result_key');

        $paginator = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('audit_log_id')
            ->paginate($perPage, ['*'], 'page', $page);

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
                ], $this->getAuditLogActionOptions()),
                'users' => array_merge([
                    ['value' => '', 'label' => 'All Users'],
                ], $this->getAuditLogUserOptions()),
                'roles' => array_merge([
                    ['value' => '', 'label' => 'All Roles'],
                ], $this->getAuditLogRoleOptions()),
                'modules' => array_merge([
                    ['value' => '', 'label' => 'All Modules'],
                ], $this->getAuditLogModuleOptions()),
                'results' => [
                    ['value' => '', 'label' => 'All Results'],
                    ['value' => 'success', 'label' => 'Success'],
                    ['value' => 'failed', 'label' => 'Failed'],
                    ['value' => 'warning', 'label' => 'Warning'],
                ],
            ],
        ]);
    }

    /**
     * Get unique action options for audit log filters.
     */
    protected function getAuditLogActionOptions(): array
    {
        return DB::table('audit_logs')
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
    }

    /**
     * Get unique user options for audit log filters.
     */
    protected function getAuditLogUserOptions(): array
    {
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

        return collect($roleValues)
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => Str::title($value),
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /** @return array<int, array{value:string,label:string}> */
    protected function getAuditLogRoleOptions(): array
    {
        return DB::table('audit_logs')
            ->whereNotNull('actor_role')
            ->where('actor_role', '!=', '')
            ->distinct()
            ->orderBy('actor_role')
            ->pluck('actor_role')
            ->map(fn (string $value): array => [
                'value' => Str::lower(trim($value)),
                'label' => $this->labelizeAuditValue($value),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{value:string,label:string}> */
    protected function getAuditLogModuleOptions(): array
    {
        return DB::table('audit_logs')
            ->whereNotNull('module_type')
            ->where('module_type', '!=', '')
            ->distinct()
            ->orderBy('module_type')
            ->pluck('module_type')
            ->map(fn (string $value): array => [
                'value' => Str::lower(trim($value)),
                'label' => $this->labelizeAuditValue($value),
            ])
            ->values()
            ->all();
    }

    /**
     * Export filtered audit logs to CSV.
     */
    public function exportAuditLogsCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'actor' => ['nullable', 'string', 'max:150'],
            'actor_role' => ['nullable', 'string', 'max:50'],
            'action_type' => ['nullable', 'string', 'max:50'],
            'module_type' => ['nullable', 'string', 'max:80'],
            'result' => ['nullable', 'string', Rule::in(['', 'success', 'failed', 'warning'])],
            'user_type' => ['nullable', 'string', Rule::in(['', 'admin', 'donor', 'system', 'other'])],
            'security_policy_only' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $actor = trim((string) ($validated['actor'] ?? ''));
        $actorRole = Str::lower(trim((string) ($validated['actor_role'] ?? '')));
        $actionType = Str::lower(trim((string) ($validated['action_type'] ?? '')));
        $moduleType = Str::lower(trim((string) ($validated['module_type'] ?? '')));
        $result = Str::lower(trim((string) ($validated['result'] ?? '')));
        $userType = Str::lower(trim((string) ($validated['user_type'] ?? '')));
        $securityPolicyOnly = (bool) ($validated['security_policy_only'] ?? false);
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $query = DB::table('audit_logs');
        $this->applyAuditLogFilters($query, $searchTerm, $actor, $actorRole, $actionType, $moduleType, $result, $userType, $securityPolicyOnly, $startDate, $endDate);

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
                'Details',
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
                    $this->safeAuditMetadataText($row->metadata ?? null),
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
     * Display the currently authenticated administrator's profile.
     *
     * Profile editing remains in the existing RBAC user-management flow so
     * this page does not introduce a second account-update endpoint.
     */
    public function profile(Request $request)
    {
        $adminId = (int) $request->session()->get('admin_id', 0);
        $columns = ['admin_id', 'full_name', 'username', 'email', 'role'];

        if (Schema::hasColumn('admins', 'created_at')) {
            $columns[] = 'created_at';
        }

        if ($this->supportsTwoFactorStorage()) {
            $columns[] = 'two_factor_enabled';
            $columns[] = 'two_factor_confirmed_at';
        }

        $admin = DB::table('admins')
            ->select($columns)
            ->where('admin_id', $adminId)
            ->first();

        if (! $admin) {
            return redirect()
                ->route('admin.login')
                ->with('error', 'Please log in to continue.');
        }

        $role = $this->normalizeRole((string) ($admin->role ?? 'staff'));

        return view('admin.profile', [
            'adminProfile' => [
                'admin_id' => (int) ($admin->admin_id ?? 0),
                'full_name' => $this->displayNameForAdmin($admin),
                'username' => (string) ($admin->username ?? ''),
                'email' => (string) ($admin->email ?? ''),
                'role' => Str::title($role),
                'role_key' => $role,
                'created_at' => (string) ($admin->created_at ?? ''),
                'two_factor_enabled' => (bool) ($admin->two_factor_enabled ?? false)
                    && ! empty($admin->two_factor_confirmed_at),
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

        // Existing databases may contain the fixed role values as "Admin" and
        // "Staff", while newer writes use lowercase values. Normalize the
        // grouped keys before exposing role counts to the RBAC UI.
        $roleUserCounts = [
            '1' => 0,
            '2' => 0,
        ];

        foreach ($roleCountsRaw as $roleName => $total) {
            $roleId = $this->normalizeRole((string) $roleName) === 'admin' ? '1' : '2';

            if ($this->isSupportedRole($this->normalizeRole((string) $roleName))) {
                $roleUserCounts[$roleId] += (int) $total;
            }
        }

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
                'role_user_counts' => $roleUserCounts,
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

        if (! $targetAdmin) {
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

        if (! $targetAdmin) {
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

        if (! $targetAdmin) {
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

        if (! $targetAdmin) {
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
    public function settings(Request $request, PrivacyLegalSettings $privacyLegalSettings)
    {
        $adminId = (int) $request->session()->get('admin_id', 0);
        $columns = ['admin_id'];

        if (Schema::hasColumn('admins', 'email')) {
            $columns[] = 'email';
        }

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
        $adminNotificationPreference = $this->getAdminNotificationPreference($adminId);
        $privacyLegalValues = $privacyLegalSettings->values();

        return view('admin.settings', [
            'privacyLegalSettings' => $privacyLegalValues,
            'settingsPayload' => [
                'page' => 'settings',
                'settings' => [
                    'general' => $this->getSystemSettingsValues(),
                    'account' => [
                        'updateUrl' => route('admin.settings.account.update'),
                    ],
                    'notifications' => [
                        'email' => (bool) ($adminNotificationPreference?->email_enabled ?? false),
                        'emailAddress' => (string) ($admin->email ?? ''),
                        'updateUrl' => route('admin.settings.notifications.update'),
                    ],
                    'security' => [
                        'twoFactor' => (bool) ($globalSecuritySettings['two_factor_required'] ?? self::SECURITY_DEFAULT_TWO_FACTOR_REQUIRED),
                        'sessionTimeout' => (string) ($globalSecuritySettings['session_timeout_minutes'] ?? self::SECURITY_DEFAULT_SESSION_TIMEOUT_MINUTES),
                        'twoFactorSetupUrl' => route('admin.2fa.setup'),
                        'updateSecurityUrl' => route('admin.settings.security.update'),
                        'currentAccountTwoFactorEnabled' => $currentAccountTwoFactorEnabled,
                    ],
                    'privacyLegal' => array_merge($privacyLegalValues, [
                        'updateUrl' => route('admin.settings.privacy-legal.update'),
                    ]),
                ],
            ],
        ]);
    }

    /**
     * Persist display/contact settings for the admin portal.
     */
    public function updateGeneralSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'system_name' => ['required', 'string', 'max:150'],
            'system_email' => ['required', 'email', 'max:150'],
            'contact_number' => ['required', 'string', 'max:30', 'regex:/^[+0-9][0-9\s-]{6,}$/'],
        ]);

        if (! $this->supportsSystemSettingsStorage()) {
            return response()->json([
                'message' => 'General settings storage is not available. Please run migrations first.',
            ], 409);
        }

        $settings = [
            'system_name' => trim((string) $validated['system_name']),
            'system_email' => Str::lower(trim((string) $validated['system_email'])),
            'contact_number' => trim((string) $validated['contact_number']),
        ];

        DB::transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                DB::table(self::SYSTEM_SETTINGS_TABLE)->updateOrInsert(
                    ['setting_key' => $key],
                    [
                        'setting_value' => $value,
                        'updated_at' => now(),
                    ]
                );
            }
        });

        $this->logAdminSettingsAudit(
            $request,
            'update',
            'Updated admin portal general settings.',
            [
                'changed_keys' => array_keys($settings),
            ]
        );

        return response()->json([
            'message' => 'General settings saved successfully.',
            'general' => array_merge($this->getSystemSettingsValues(), [
                'updateUrl' => route('admin.settings.general.update'),
            ]),
        ]);
    }

    /**
     * Persist public policy overrides and cookie-banner presentation settings.
     */
    public function updatePrivacyLegalSettings(
        Request $request,
        PrivacyLegalSettings $privacyLegalSettings
    ): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'privacy_policy' => ['nullable', 'string', 'min:50', 'max:' . PrivacyLegalSettings::POLICY_MAX_LENGTH],
            'terms_and_conditions' => ['nullable', 'string', 'min:50', 'max:' . PrivacyLegalSettings::POLICY_MAX_LENGTH],
            'cookie_policy' => ['nullable', 'string', 'min:50', 'max:' . PrivacyLegalSettings::POLICY_MAX_LENGTH],
            'enforce_cookie_consent_banner' => ['required', 'boolean'],
        ]);

        if (! $privacyLegalSettings->storageAvailable()) {
            $message = 'Privacy and legal settings storage is not available. Please run migrations first.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 409)
                : redirect()->route('admin.settings')->with('error', $message);
        }

        $saved = $privacyLegalSettings->save($validated);

        $this->logAdminSettingsAudit(
            $request,
            'update',
            'Updated privacy and legal settings.',
            [
                'custom_privacy_policy' => $saved['privacyPolicy'] !== null,
                'custom_terms_and_conditions' => $saved['termsAndConditions'] !== null,
                'custom_cookie_policy' => $saved['cookiePolicy'] !== null,
                'enforce_cookie_consent_banner' => $saved['enforceCookieConsentBanner'],
            ]
        );

        $message = 'Privacy and legal settings saved successfully.';
        $responsePayload = array_merge($saved, [
            'updateUrl' => route('admin.settings.privacy-legal.update'),
        ]);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('admin.settings')
                ->with('success', $message)
                ->with('settings_tab', 'privacy-legal');
        }

        return response()->json([
            'message' => $message,
            'privacyLegal' => $responsePayload,
        ]);
    }

    /**
     * Update the currently authenticated admin's password.
     */
    public function updateAccountSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $adminId = (int) $request->session()->get('admin_id', 0);
        $columns = ['admin_id', 'password'];

        if (Schema::hasColumn('admins', 'remember_token')) {
            $columns[] = 'remember_token';
        }

        if (Schema::hasColumn('admins', 'remember_token_expires_at')) {
            $columns[] = 'remember_token_expires_at';
        }

        $admin = DB::table('admins')
            ->select($columns)
            ->where('admin_id', $adminId)
            ->first();

        if (! $admin || ! Hash::check((string) $validated['current_password'], (string) ($admin->password ?? ''))) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        $updatePayload = [
            'password' => Hash::make((string) $validated['new_password']),
        ];

        if (Schema::hasColumn('admins', 'remember_token')) {
            $updatePayload['remember_token'] = null;
        }

        if (Schema::hasColumn('admins', 'remember_token_expires_at')) {
            $updatePayload['remember_token_expires_at'] = null;
        }

        DB::table('admins')
            ->where('admin_id', $adminId)
            ->update($updatePayload);

        $this->logAdminSettingsAudit(
            $request,
            'update',
            'Updated the current admin account password.',
            [
                'password_changed' => true,
                'remember_tokens_revoked' => true,
            ]
        );

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Persist the current admin's email notification preference.
     */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_enabled' => ['required', 'boolean'],
        ]);

        if (! $this->supportsAdminNotificationPreferencesStorage()) {
            return response()->json([
                'message' => 'Email notification settings storage is not available. Please run migrations first.',
            ], 409);
        }

        $adminId = (int) $request->session()->get('admin_id', 0);
        $admin = DB::table('admins')
            ->select('admin_id', 'email')
            ->where('admin_id', $adminId)
            ->first();

        if (! $admin) {
            return response()->json([
                'message' => 'Authenticated admin account not found.',
            ], 401);
        }

        $emailEnabled = (bool) $validated['email_enabled'];
        $emailAddress = trim((string) ($admin->email ?? ''));

        if ($emailEnabled && filter_var($emailAddress, FILTER_VALIDATE_EMAIL) === false) {
            return response()->json([
                'message' => 'Add a valid email address to the admin account before enabling email notifications.',
            ], 422);
        }

        AdminNotificationPreference::query()->updateOrCreate(
            ['admin_id' => $adminId],
            ['email_enabled' => $emailEnabled]
        );

        return response()->json([
            'message' => $emailEnabled
                ? 'Email notifications enabled successfully.'
                : 'Email notifications disabled successfully.',
            'notifications' => [
                'email' => $emailEnabled,
                'emailAddress' => $emailAddress,
                'updateUrl' => route('admin.settings.notifications.update'),
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

        if (! $this->supportsGlobalSecuritySettingsStorage()) {
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
        $requiresEnrollment = (bool) $validated['two_factor_required'] && ! $currentAccountTwoFactorEnabled;

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

        if (! $this->supportsTwoFactorStorage()) {
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

        if (! $twoFactorEnabled) {
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
            'confirmedAt' => ! empty($admin->two_factor_confirmed_at)
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

        if (! $this->isGlobalTwoFactorRequired() || ! $this->supportsTwoFactorStorage()) {
            return $defaultPayload;
        }

        $admin = $this->getCurrentAdminForTwoFactor($request);
        if ($admin === null) {
            return $defaultPayload;
        }

        $setupData = $this->prepareTwoFactorSetupViewData($request, $admin);

        return [
            'required' => ! (bool) ($setupData['twoFactorEnabled'] ?? false),
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

        if (! $this->supportsTwoFactorStorage()) {
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

        if (! $this->verifyTotpCode($secret, (string) $validated['otp'])) {
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

        if (! $this->isTwoFactorEnabledForAdmin($admin)) {
            return redirect()
                ->route('admin.2fa.setup')
                ->with('error', 'Two-factor authentication is already disabled.');
        }

        if (! Hash::check((string) $validated['current_password'], (string) ($admin->password ?? ''))) {
            return back()->withErrors([
                'current_password' => 'Current password does not match.',
            ]);
        }

        $secret = $this->decryptTwoFactorSecret((string) ($admin->two_factor_secret ?? ''));
        if ($secret === null || ! $this->verifyTotpCode($secret, (string) $validated['otp'])) {
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

        if (! $rememberCookie || ! $this->supportsRememberMeStorage()) {
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
        if (! is_numeric($adminId) || trim($plainToken) === '') {
            $this->clearRememberMeToken();

            return false;
        }

        $admin = DB::table('admins')
            ->where('admin_id', (int) $adminId)
            ->first();

        if (! $admin) {
            $this->clearRememberMeToken();

            return false;
        }

        $role = $this->normalizeRole((string) ($admin->role ?? ''));
        if (! $this->isSupportedRole($role)) {
            $this->clearRememberMeToken((int) $admin->admin_id);

            return false;
        }

        $expiresAt = ! empty($admin->remember_token_expires_at)
            ? Carbon::parse($admin->remember_token_expires_at)
            : null;

        $isTokenValid = ! empty($admin->remember_token)
            && $expiresAt !== null
            && ! $expiresAt->isPast()
            && Hash::check($plainToken, $admin->remember_token);

        if (! $isTokenValid) {
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
        if (! Schema::hasTable('admins')) {
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

        if (! $this->supportsGlobalSecuritySettingsStorage()) {
            return $defaults;
        }

        $row = DB::table(self::SECURITY_SETTINGS_TABLE)
            ->select('enforce_two_factor', 'session_timeout_minutes')
            ->orderByDesc('admin_security_setting_id')
            ->first();

        if (! $row) {
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
        if (! $this->supportsGlobalSecuritySettingsStorage()) {
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
        if (! Schema::hasTable(self::SECURITY_SETTINGS_TABLE)) {
            return false;
        }

        return Schema::hasColumn(self::SECURITY_SETTINGS_TABLE, 'enforce_two_factor')
            && Schema::hasColumn(self::SECURITY_SETTINGS_TABLE, 'session_timeout_minutes');
    }

    /**
     * Resolve the current admin's email notification preference.
     */
    private function getAdminNotificationPreference(int $adminId): ?AdminNotificationPreference
    {
        if ($adminId <= 0 || ! $this->supportsAdminNotificationPreferencesStorage()) {
            return null;
        }

        return AdminNotificationPreference::query()
            ->where('admin_id', $adminId)
            ->first();
    }

    /**
     * Detect whether the email preference migration has been applied.
     */
    private function supportsAdminNotificationPreferencesStorage(): bool
    {
        if (! Schema::hasTable(self::ADMIN_NOTIFICATION_PREFERENCES_TABLE)) {
            return false;
        }

        return Schema::hasColumn(self::ADMIN_NOTIFICATION_PREFERENCES_TABLE, 'admin_id')
            && Schema::hasColumn(self::ADMIN_NOTIFICATION_PREFERENCES_TABLE, 'email_enabled');
    }

    /**
     * Resolve persisted portal display/contact settings with safe defaults.
     *
     * @return array{systemName:string,systemEmail:string,contactNumber:string}
     */
    private function getSystemSettingsValues(): array
    {
        $defaults = [
            'systemName' => 'eDonate',
            'systemEmail' => 'admin@edonate.local',
            'contactNumber' => '+63 917 123 4567',
        ];

        if (! $this->supportsSystemSettingsStorage()) {
            return $defaults;
        }

        $rows = DB::table(self::SYSTEM_SETTINGS_TABLE)
            ->whereIn('setting_key', ['system_name', 'system_email', 'contact_number'])
            ->pluck('setting_value', 'setting_key');

        return [
            'systemName' => trim((string) ($rows['system_name'] ?? $defaults['systemName'])) ?: $defaults['systemName'],
            'systemEmail' => trim((string) ($rows['system_email'] ?? $defaults['systemEmail'])) ?: $defaults['systemEmail'],
            'contactNumber' => trim((string) ($rows['contact_number'] ?? $defaults['contactNumber'])) ?: $defaults['contactNumber'],
        ];
    }

    /**
     * Detect whether the optional portal-settings migration is available.
     */
    private function supportsSystemSettingsStorage(): bool
    {
        if (! Schema::hasTable(self::SYSTEM_SETTINGS_TABLE)) {
            return false;
        }

        return Schema::hasColumn(self::SYSTEM_SETTINGS_TABLE, 'setting_key')
            && Schema::hasColumn(self::SYSTEM_SETTINGS_TABLE, 'setting_value');
    }

    /**
     * Determine if admin account currently enforces Google Authenticator.
     */
    private function isTwoFactorEnabledForAdmin(?object $admin): bool
    {
        if (! $this->supportsTwoFactorStorage() || ! $admin) {
            return false;
        }

        return (bool) ($admin->two_factor_enabled ?? false)
            && trim((string) ($admin->two_factor_secret ?? '')) !== '';
    }

    /**
     * Stage pending 2FA challenge data in session after password validation.
     */
    private function stagePendingTwoFactorLogin(
        Request $request,
        object $admin,
        string $role,
        bool $rememberRequested,
        string $primaryMethod = 'password'
    ): void
    {
        $request->session()->regenerate();

        $pending = [
            'admin_id' => (int) ($admin->admin_id ?? 0),
            'email' => Str::lower(trim((string) ($admin->email ?? ''))),
            'role' => $this->normalizeRole($role),
            'remember' => $rememberRequested,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TWO_FACTOR_PENDING_TTL_MINUTES)->timestamp,
            'primary_method' => $primaryMethod,
        ];

        $request->session()->put(self::TWO_FACTOR_PENDING_SESSION_KEY, $pending);
    }

    /**
     * Read and validate pending 2FA login state.
     *
     * @return array<string, mixed>|null
     */
    private function getPendingTwoFactorLogin(Request $request): ?array
    {
        $pending = $request->session()->get(self::TWO_FACTOR_PENDING_SESSION_KEY);

        if (! is_array($pending) || ! is_numeric($pending['admin_id'] ?? null)) {
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
     * Finish an already authenticated password + second-factor login.
     *
     * @param array<string, mixed> $pending
     */
    private function completePendingTwoFactorLogin(
        Request $request,
        array $pending,
        object $admin,
        string $method
    ): RedirectResponse {
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

        if (! empty($pending['remember'])) {
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
                'two_factor_method' => $method,
            ]
        );

        $this->pushFirebaseSecurityEvent('admin_2fa_success', [
            'admin_id' => (int) ($admin->admin_id ?? 0),
            'method' => $method,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route($this->dashboardRouteForRole($role))
            ->with('success', 'Two-factor authentication successful.');
    }

    /**
     * Verify TOTP code against decrypted secret.
     */
    private function verifyTotpCode(string $secret, string $code): bool
    {
        $normalizedCode = preg_replace('/\s+/', '', trim($code)) ?? '';
        if (! preg_match('/^\d{6}$/', $normalizedCode)) {
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

        if (! is_array($decoded)) {
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
        if (! $this->supportsTwoFactorStorage()) {
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
            if (! Hash::check($normalizedInput, $hash)) {
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
                new SvgImageBackEnd
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
        if ($email === '' || ! Str::contains($email, '@')) {
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
        return new Google2FA;
    }

    /**
     * Best-effort security-event write. Authentication must never depend on
     * Realtime Database availability, OAuth token exchange, or database rules.
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
            Log::warning('Firebase admin security event write failed.', [
                'failure_type' => 'firebase_audit_write_failed',
                'event_type' => $eventType,
                'exception_class' => $exception::class,
            ]);
        }
    }

    /**
     * Build appointment listing query used by admin appointment management module.
     */
    private function appointmentManagementBaseQuery()
    {
        $statusExpression = $this->appointmentStatusExpression();
        $centerExpression = $this->appointmentCenterExpression();

        return DB::table('appointments as ap')
            ->leftJoin('donors as d', 'd.donor_id', '=', 'ap.donor_id')
            ->leftJoinSub($this->appointmentLatestDonorAuthQuery(), 'da_latest', function ($join): void {
                $join->on('da_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('donor_authentication as da', 'da.auth_id', '=', 'da_latest.latest_auth_id')
            ->leftJoin('blood_types as bt', 'bt.blood_type_id', '=', 'd.blood_type_id')
            ->leftJoin('admins as blood_type_verifier', 'blood_type_verifier.admin_id', '=', 'd.blood_type_verified_by_admin_id')
            ->leftJoin('locations as l', 'l.location_id', '=', 'd.location_id')
            ->select([
                'ap.appointment_id',
                'ap.donor_id',
                'ap.appointment_date',
                'ap.appointment_time',
                'ap.status',
                'ap.created_at',
                'd.first_name',
                'd.last_name',
                'd.contact_number',
                'da.email',
                'bt.blood_type',
            ])
            ->selectRaw('('.$centerExpression.') as center_label')
            ->selectRaw('('.$statusExpression.') as normalized_status');
    }

    /**
     * Resolve latest donor authentication row per donor to avoid duplicate joins.
     */
    private function appointmentLatestDonorAuthQuery()
    {
        return DB::table('donor_authentication')
            ->select([
                'donor_id',
                DB::raw('MAX(auth_id) as latest_auth_id'),
            ])
            ->groupBy('donor_id');
    }

    /**
     * Build SQL expression for normalized appointment status buckets.
     */
    private function appointmentStatusExpression(string $appointmentsAlias = 'ap'): string
    {
        return "CASE
        WHEN LOWER(COALESCE({$appointmentsAlias}.status, '')) 
IN ('confirmed', 'approved', 'scheduled') THEN 'confirmed'
WHEN LOWER(COALESCE({$appointmentsAlias}.status, '')) 
IN ('completed', 'complete', 'done') THEN 'completed'
WHEN LOWER(COALESCE({$appointmentsAlias}.status, ''))
IN ('checked_in', 'checked in') THEN 'checked_in'
WHEN LOWER(COALESCE({$appointmentsAlias}.status, ''))
IN ('deferred_on_site', 'deferred on site', 'onsite_deferred') THEN 'deferred_on_site'
            WHEN LOWER(COALESCE({$appointmentsAlias}.status, '')) IN ('pending', 'pending approval', 'for approval') THEN 'pending'
            WHEN LOWER(COALESCE({$appointmentsAlias}.status, '')) IN ('cancelled', 'canceled', 'rejected', 'declined') THEN 'cancelled'
            WHEN LOWER(COALESCE({$appointmentsAlias}.status, '')) IN ('no_show', 'no show', 'noshow') THEN 'no_show'
            WHEN LOWER(COALESCE({$appointmentsAlias}.status, '')) IN ('rescheduled', 'reschedule requested') THEN 'rescheduled'
            ELSE 'pending'
        END";
    }

    /**
     * Normalize an appointment status string into one of: confirmed, pending, cancelled, rescheduled.
     */
    private function normalizeAppointmentStatusValue(string $status): string
    {
        $status = Str::lower(trim($status));

        if (in_array($status, ['confirmed', 'approved', 'scheduled'], true)) {
            return 'confirmed';
        }
        if (in_array($status, ['completed', 'complete', 'done'], true)) {
            return 'completed';
        }
        if (in_array($status, ['checked_in', 'checked in'], true)) {
            return 'checked_in';
        }
        if (in_array($status, ['deferred_on_site', 'deferred on site', 'onsite_deferred'], true)) {
            return 'deferred_on_site';
        }
        if (in_array($status, ['pending', 'pending approval', 'for approval'], true)) {
            return 'pending';
        }
        if (in_array($status, ['cancelled', 'canceled', 'rejected', 'declined'], true)) {
            return 'cancelled';
        }
        if (in_array($status, ['rescheduled', 'reschedule requested'], true)) {
            return 'rescheduled';
        }
        if (in_array($status, ['no_show', 'no show', 'noshow'], true)) {
            return 'no_show';
        }

        return 'pending';
    }

    /**
     * Build SQL expression for readable center label from donor location.
     */
    private function appointmentCenterExpression(string $locationsAlias = 'l'): string
    {
        return "TRIM(COALESCE(NULLIF({$locationsAlias}.city, ''), NULLIF({$locationsAlias}.province, ''), NULLIF({$locationsAlias}.barangay_name, ''), NULLIF({$locationsAlias}.street_address, ''), 'N/A'))";
    }

    /**
     * Resolve available center filter values for appointment management page.
     *
     * @return array<int, string>
     */
    private function appointmentManagementCenterOptions(): array
    {
        $centerExpression = $this->appointmentCenterExpression();

        return DB::table('donors as d')
            ->leftJoin('locations as l', 'l.location_id', '=', 'd.location_id')
            ->whereNotNull('d.location_id')
            ->selectRaw('('.$centerExpression.') as center_label')
            ->distinct()
            ->orderBy('center_label')
            ->pluck('center_label')
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '' && Str::lower($value) !== 'n/a')
            ->values()
            ->all();
    }

    /**
     * Normalize appointment row payload for admin appointment front-end shape.
     *
     * @return array<string, mixed>
     */
    private function transformAppointmentManagementRow(object $entry): array
    {
        $donorName = trim((string) ($entry->first_name ?? '').' '.(string) ($entry->last_name ?? ''));
        if ($donorName === '') {
            $donorName = 'Unknown Donor';
        }

        $status = Str::lower(trim((string) ($entry->normalized_status ?? 'pending')));
        if (! in_array($status, ['confirmed', 'pending', 'cancelled', 'rescheduled', 'checked_in', 'completed', 'deferred_on_site', 'no_show'], true)) {
            $status = 'pending';
        }

        return [
            'appointment_id' => (int) ($entry->appointment_id ?? 0),
            'appointment_code' => 'AP'.str_pad((string) ((int) ($entry->appointment_id ?? 0)), 3, '0', STR_PAD_LEFT),
            'donor_id' => (int) ($entry->donor_id ?? 0),
            'donor_code' => 'D'.str_pad((string) ((int) ($entry->donor_id ?? 0)), 3, '0', STR_PAD_LEFT),
            'donor_name' => $donorName,
            'donor_email' => trim((string) ($entry->email ?? '')),
            'blood_type' => trim((string) ($entry->blood_type ?? '')),
            'appointment_date' => ! empty($entry->appointment_date) ? (string) $entry->appointment_date : null,
            'appointment_time' => ! empty($entry->appointment_time) ? (string) $entry->appointment_time : null,
            'center_label' => trim((string) ($entry->center_label ?? '')) !== ''
                ? trim((string) $entry->center_label)
                : 'N/A',
            'status' => $status,
        ];
    }

    private function donationProcessingBaseQuery()
    {
        $statusExpression = $this->appointmentStatusExpression('ap');

        return DB::table('appointments as ap')
            ->leftJoin('donors as d', 'd.donor_id', '=', 'ap.donor_id')
            ->leftJoin('donation_events as de', 'de.event_id', '=', 'ap.event_id')
            ->leftJoin('donation_records as dr', 'dr.appointment_id', '=', 'ap.appointment_id')
            ->leftJoin('blood_types as bt', 'bt.blood_type_id', '=', 'd.blood_type_id')
            ->leftJoin('blood_types as verified_bt', 'verified_bt.blood_type_id', '=', 'dr.verified_blood_type_id')
            ->leftJoinSub($this->appointmentLatestDonorAuthQuery(), 'da_latest', function ($join): void {
                $join->on('da_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('donor_authentication as da', 'da.auth_id', '=', 'da_latest.latest_auth_id')
            ->leftJoinSub($this->userManagementLatestEligibilityQuery(), 'es_latest', function ($join): void {
                $join->on('es_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('eligibility_status as es', 'es.eligibility_id', '=', 'es_latest.latest_eligibility_id')
            ->leftJoin('admins as recorder', 'recorder.admin_id', '=', 'dr.recorded_by_admin_id')
            ->leftJoin('admins as blood_type_verifier', 'blood_type_verifier.admin_id', '=', 'd.blood_type_verified_by_admin_id')
            ->select([
                'ap.appointment_id',
                'ap.donor_id',
                'ap.event_id',
                'ap.appointment_date',
                'ap.appointment_time',
                'ap.status',
                'ap.checked_in_at',
                'ap.completed_at',
                'ap.cancellation_reason',
                'ap.created_at as booked_at',
                'ap.donation_center',
                'd.first_name',
                'd.last_name',
                'd.verification_status',
                'd.blood_type_id',
                'd.blood_type_status',
                'd.blood_type_verified_at',
                'd.blood_type_verified_by_admin_id',
                'da.email',
                'bt.blood_type',
                'blood_type_verifier.full_name as blood_type_verified_by_name',
                'blood_type_verifier.username as blood_type_verified_by_username',
                'de.title as event_title',
                'de.location_name as event_location_name',
                'es.status as eligibility_status',
                'es.next_eligible_date',
                'dr.donation_id',
                'dr.donation_date',
                'dr.donation_status',
                'dr.blood_units',
                'dr.verified_blood_type_id',
                'verified_bt.blood_type as verified_blood_type',
                'dr.remarks',
                'dr.deferred_reason',
                'dr.recorded_by_admin_id',
                'recorder.full_name as recorded_by_name',
                'recorder.username as recorded_by_username',
            ])
            ->selectRaw('('.$statusExpression.') as normalized_status')
            ->whereRaw('('.$statusExpression.') IN (?, ?, ?, ?, ?, ?)', [
                'confirmed',
                'checked_in',
                'completed',
                'deferred_on_site',
                'no_show',
                'cancelled',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformDonationProcessingRow(object $entry): array
    {
        $donorName = trim((string) ($entry->first_name ?? '').' '.(string) ($entry->last_name ?? ''));
        $donorName = $donorName !== '' ? $donorName : 'Unknown Donor';
        $status = Str::lower(trim((string) ($entry->normalized_status ?? 'pending')));
        $center = trim((string) ($entry->donation_center ?? ''));
        $center = $center !== '' ? $center : trim((string) ($entry->event_location_name ?? ''));
        $recordedBy = trim((string) ($entry->recorded_by_name ?? ''));
        $recordedBy = $recordedBy !== '' ? $recordedBy : trim((string) ($entry->recorded_by_username ?? ''));

        return [
            'appointment_id' => (int) ($entry->appointment_id ?? 0),
            'appointment_code' => 'AP'.str_pad((string) ((int) ($entry->appointment_id ?? 0)), 3, '0', STR_PAD_LEFT),
            'donation_id' => isset($entry->donation_id) ? (int) $entry->donation_id : null,
            'donation_code' => isset($entry->donation_id) ? 'DR'.str_pad((string) ((int) $entry->donation_id), 3, '0', STR_PAD_LEFT) : null,
            'donor_id' => (int) ($entry->donor_id ?? 0),
            'donor_name' => $donorName,
            'donor_email' => trim((string) ($entry->email ?? '')),
            'blood_type' => trim((string) ($entry->blood_type ?? '')),
            'blood_type_id' => isset($entry->blood_type_id) ? (int) $entry->blood_type_id : null,
            'blood_type_status' => Str::lower(trim((string) ($entry->blood_type_status ?? 'not_yet_determined'))),
            'event_id' => isset($entry->event_id) ? (int) $entry->event_id : null,
            'event_title' => trim((string) ($entry->event_title ?? 'Legacy appointment')),
            'appointment_date' => ! empty($entry->appointment_date) ? (string) $entry->appointment_date : null,
            'appointment_time' => ! empty($entry->appointment_time) ? (string) $entry->appointment_time : null,
            'center_label' => $center !== '' ? $center : 'N/A',
            'status' => $status,
            'checked_in_at' => ! empty($entry->checked_in_at) ? (string) $entry->checked_in_at : null,
            'completed_at' => ! empty($entry->completed_at) ? (string) $entry->completed_at : null,
            'booked_at' => ! empty($entry->booked_at) ? (string) $entry->booked_at : null,
            'verification_status' => Str::lower(trim((string) ($entry->verification_status ?? 'unverified'))),
            'eligibility_status' => Str::lower(trim((string) ($entry->eligibility_status ?? 'unknown'))),
            'next_eligible_date' => ! empty($entry->next_eligible_date) ? (string) $entry->next_eligible_date : null,
            'donation_status' => $entry->donation_status ? Str::lower((string) $entry->donation_status) : null,
            'blood_units' => is_numeric($entry->blood_units ?? null) ? (int) $entry->blood_units : null,
            'verified_blood_type_id' => isset($entry->verified_blood_type_id) ? (int) $entry->verified_blood_type_id : null,
            'verified_blood_type' => trim((string) ($entry->verified_blood_type ?? '')),
            'remarks' => $entry->remarks,
            'deferred_reason' => $entry->deferred_reason,
            'recorded_by' => $recordedBy !== '' ? $recordedBy : null,
            'actions' => [
                'can_complete' => $status === 'checked_in',
                'can_defer' => $status === 'checked_in',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function donationProcessingEventOptions(): array
    {
        if (! Schema::hasTable('donation_events')) {
            return [];
        }

        return DB::table('donation_events')
            ->orderByDesc('event_date')
            ->limit(100)
            ->get(['event_id', 'title', 'event_date'])
            ->map(fn (object $event): array => [
                'event_id' => (int) $event->event_id,
                'title' => trim((string) $event->title),
                'event_date' => (string) $event->event_date,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function donationProcessingCenterOptions(): array
    {
        return DB::table('appointments')
            ->select('donation_center')
            ->whereNotNull('donation_center')
            ->distinct()
            ->orderBy('donation_center')
            ->pluck('donation_center')
            ->merge(
                Schema::hasTable('donation_events')
                    ? DB::table('donation_events')->whereNotNull('location_name')->distinct()->pluck('location_name')
                    : collect()
            )
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique(fn (string $value): string => Str::lower($value))
            ->values()
            ->all();
    }

    /**
     * Build the base donation records query used by donation records page.
     */
    private function donationRecordsBaseQuery()
    {
        $centerExpression = $this->appointmentCenterExpression();
        $statusExpression = $this->donationRecordStatusExpression();

        return DB::table('donation_records as dr')
            ->leftJoin('donors as d', 'd.donor_id', '=', 'dr.donor_id')
            ->leftJoinSub($this->appointmentLatestDonorAuthQuery(), 'da_latest', function ($join): void {
                $join->on('da_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('donor_authentication as da', 'da.auth_id', '=', 'da_latest.latest_auth_id')
            ->leftJoin('blood_types as bt', 'bt.blood_type_id', '=', 'd.blood_type_id')
            ->leftJoin('locations as l', 'l.location_id', '=', 'd.location_id')
            ->leftJoinSub($this->userManagementLatestEligibilityQuery(), 'es_latest', function ($join): void {
                $join->on('es_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('eligibility_status as es', 'es.eligibility_id', '=', 'es_latest.latest_eligibility_id')
            ->select([
                'dr.donation_id',
                'dr.donor_id',
                'dr.appointment_id',
                'dr.donation_date',
                'dr.blood_units',
                'dr.remarks',
                'd.first_name',
                'd.last_name',
                'da.email',
                'bt.blood_type',
                'es.next_eligible_date',
            ])
            ->selectRaw('('.$centerExpression.') as center_label')
            ->selectRaw('('.$statusExpression.') as derived_status');
    }

    /**
     * Build SQL expression for normalized donation record status buckets.
     */
    private function donationRecordStatusExpression(string $donationsAlias = 'dr'): string
    {
        return "CASE
            WHEN LOWER(COALESCE({$donationsAlias}.donation_status, '')) IN ('completed', 'deferred', 'failed') THEN LOWER({$donationsAlias}.donation_status)
            WHEN {$donationsAlias}.donation_date IS NOT NULL THEN 'completed'
            WHEN LOWER(COALESCE({$donationsAlias}.remarks, '')) LIKE '%defer%' THEN 'deferred'
            ELSE 'pending'
        END";
    }

    /**
     * Build public statistics displayed on the admin login page.
     *
     * @return array<string, string>
     */
    private function buildLoginStats(): array
    {
        // Login must remain available while an older/incomplete local schema
        // is being restored. The Hostinger dump contains this table, but a
        // database imported without the Phase 7 tables must not turn a public
        // login page into a 500 response.
        $successfulDonationCount = $this->dashboardTableHasColumns(
            'donation_records',
            ['donation_date']
        )
            ? (int) $this->dashboardSuccessfulDonationQuery()->count()
            : 0;

        $donorCount = $this->dashboardTableExists('donors')
            ? (int) DB::table('donors')->count()
            : 0;
        $livesSavedCount = $successfulDonationCount * 3;

        return [
            'donors' => $this->formatCompactStatNumber($donorCount),
            'donations' => $this->formatCompactStatNumber($successfulDonationCount),
            'lives_saved' => $this->formatCompactStatNumber($livesSavedCount),
        ];
    }

    /**
     * Format large statistic values for compact login-page display.
     */
    private function formatCompactStatNumber(int $value): string
    {
        if ($value < 1000) {
            return (string) $value;
        }

        $compactValue = round($value / 1000, 1);
        $formatted = number_format($compactValue, 1, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted.'k+';
    }

    /**
     * Build all database-backed data shown on the admin dashboard.
     *
     * @return array<string, mixed>
     */
    private function buildAdminDashboardPayload(): array
    {
        $unreadNotifications = $this->dashboardTry('notification_count', function (): int {
            return $this->dashboardUnreadNotificationCount();
        }, 0);

        return [
            'stats' => $this->dashboardTry('stats', function (): array {
                return $this->buildAdminDashboardStats();
            }, $this->emptyDashboardStats()),
            'notification_count' => $unreadNotifications,
            'notification_badge' => [
                'label' => $unreadNotifications > 9 ? '9+' : (string) $unreadNotifications,
                'visible' => $unreadNotifications > 0,
            ],
            'notification_banner' => $this->dashboardTry('notification_banner', function (): ?array {
                return $this->dashboardLatestUnreadAdminNotification();
            }, null),
            'monthly_donations' => $this->dashboardTry('monthly_donations', function (): array {
                return $this->buildDashboardMonthlyDonations();
            }, $this->emptyDashboardMonthlyDonations()),
            'blood_type_distribution' => $this->dashboardTry('blood_type_distribution', function (): array {
                return $this->buildDashboardBloodTypeDistribution();
            }, []),
            'recent_activities' => $this->dashboardTry('recent_activities', function (): array {
                return $this->buildDashboardRecentActivities();
            }, []),
            'pending_approvals' => $this->dashboardTry('pending_approvals', function (): array {
                return $this->buildDashboardPendingApprovals();
            }, []),
            'links' => [
                'map' => route('admin.blood-availability-mapping'),
                'activities' => route('admin.audit-logs'),
                'approvals' => route('admin.eligibility.index'),
            ],
        ];
    }

    /**
     * Run an optional dashboard data builder without allowing it to break the page.
     */
    private function dashboardTry(string $section, callable $callback, $fallback)
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            logger()->warning('Admin dashboard data section failed.', [
                'section' => $section,
                'error' => $exception->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * Safely check table existence for optional dashboard data.
     */
    private function dashboardTableExists(string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $cache[$table] = Schema::hasTable($table);
        } catch (Throwable $exception) {
            logger()->warning('Admin dashboard table check failed.', [
                'table' => $table,
                'error' => $exception->getMessage(),
            ]);

            $cache[$table] = false;
        }

        return $cache[$table];
    }

    /**
     * Safely check columns before dashboard queries touch optional schema.
     *
     * @param  array<int, string>  $columns
     */
    private function dashboardTableHasColumns(string $table, array $columns): bool
    {
        if (! $this->dashboardTableExists($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! $this->dashboardColumnExists($table, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Safely check a single column for optional dashboard queries.
     */
    private function dashboardColumnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table.'.'.$column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $cache[$key] = Schema::hasColumn($table, $column);
        } catch (Throwable $exception) {
            logger()->warning('Admin dashboard column check failed.', [
                'table' => $table,
                'column' => $column,
                'error' => $exception->getMessage(),
            ]);

            $cache[$key] = false;
        }

        return $cache[$key];
    }

    /**
     * Empty summary card payload used when dashboard data is unavailable.
     *
     * @return array<string, array<string, string>>
     */
    private function emptyDashboardStats(): array
    {
        return [
            'total_donors' => ['value' => '0', 'change' => '0% this month'],
            'successful_donations' => ['value' => '0', 'change' => '0% this month'],
            'upcoming_appointments' => ['value' => '0', 'change' => '0% this month'],
            'donation_records' => ['value' => '0', 'change' => '0% this month'],
        ];
    }

    /**
     * Empty chart payload used when donation data is unavailable.
     *
     * @return array<string, mixed>
     */
    private function emptyDashboardMonthlyDonations(): array
    {
        return [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'values' => array_fill(0, 12, 0),
            'maxY' => 5,
            'stepY' => 1,
        ];
    }

    /**
     * Build dashboard summary card data.
     *
     * @return array<string, array<string, string>>
     */
    private function buildAdminDashboardStats(): array
    {
        $donorTotal = $this->dashboardTableExists('donors')
            ? (int) DB::table('donors')->count()
            : 0;
        $successfulDonationTotal = $this->dashboardSuccessfulDonationQuery()->count();
        $upcomingAppointmentTotal = $this->dashboardUpcomingAppointmentQuery()->count();
        $donationRecordTotal = $this->dashboardTableExists('donation_records')
            ? (int) DB::table('donation_records')->count()
            : 0;

        $verifiedDonorTotal = $this->dashboardTableHasColumns('donors', ['verification_status'])
            ? (int) DB::table('donors')->whereRaw("LOWER(COALESCE(verification_status, '')) = ?", ['verified'])->count()
            : 0;
        $pendingVerificationTotal = $this->dashboardTableHasColumns('donors', ['verification_status'])
            ? (int) DB::table('donors')->whereRaw("LOWER(COALESCE(verification_status, '')) = ?", ['pending'])->count()
            : 0;
        $eligibleDonorTotal = 0;
        if ($this->dashboardTableHasColumns('eligibility_status', ['eligibility_id', 'donor_id', 'status'])) {
            $latestEligibilityIds = DB::table('eligibility_status')
                ->selectRaw('MAX(eligibility_id) as latest_eligibility_id')
                ->groupBy('donor_id');
            $eligibleDonorTotal = (int) DB::table('eligibility_status')
                ->whereIn('eligibility_id', $latestEligibilityIds)
                ->whereRaw("LOWER(COALESCE(status, '')) = ?", ['eligible'])
                ->count();
        }
        $openBloodRequestTotal = $this->dashboardTableHasColumns('blood_requests', ['status'])
            ? (int) DB::table('blood_requests')->whereIn('status', ['open', 'in_progress'])->count()
            : 0;
        $emergencyBloodRequestTotal = $this->dashboardTableHasColumns('blood_requests', ['status', 'urgency'])
            ? (int) DB::table('blood_requests')->whereIn('status', ['open', 'in_progress'])->where('urgency', 'emergency')->count()
            : 0;
        $lowStockTotal = 0;
        $outOfStockTotal = 0;
        if ($this->dashboardTableHasColumns('facility_blood_inventory', ['available_units', 'low_stock_threshold'])) {
            $inventoryRows = DB::table('facility_blood_inventory')
                ->get(['available_units', 'low_stock_threshold']);
            foreach ($inventoryRows as $inventoryRow) {
                $available = max(0, (int) $inventoryRow->available_units);
                $threshold = max(0, (int) $inventoryRow->low_stock_threshold);
                if ($available <= 0) {
                    $outOfStockTotal++;
                } elseif ($available <= $threshold) {
                    $lowStockTotal++;
                }
            }
        }

        [$currentStart, $currentEnd, $previousStart, $previousEnd] = $this->dashboardMonthRanges();

        $donorCurrent = $this->dashboardTableHasColumns('donors', ['date_registered'])
            ? (int) DB::table('donors')
                ->whereDate('date_registered', '>=', $currentStart)
                ->whereDate('date_registered', '<=', $currentEnd)
                ->count()
            : 0;
        $donorPrevious = $this->dashboardTableHasColumns('donors', ['date_registered'])
            ? (int) DB::table('donors')
                ->whereDate('date_registered', '>=', $previousStart)
                ->whereDate('date_registered', '<=', $previousEnd)
                ->count()
            : 0;

        $successfulDonationCurrent = $this->dashboardSuccessfulDonationQuery()
            ->whereDate('dr.donation_date', '>=', $currentStart)
            ->whereDate('dr.donation_date', '<=', $currentEnd)
            ->count();
        $successfulDonationPrevious = $this->dashboardSuccessfulDonationQuery()
            ->whereDate('dr.donation_date', '>=', $previousStart)
            ->whereDate('dr.donation_date', '<=', $previousEnd)
            ->count();

        $appointmentCurrent = $this->dashboardSchedulableAppointmentQuery()
            ->whereDate('ap.appointment_date', '>=', $currentStart)
            ->whereDate('ap.appointment_date', '<=', $currentEnd)
            ->count();
        $appointmentPrevious = $this->dashboardSchedulableAppointmentQuery()
            ->whereDate('ap.appointment_date', '>=', $previousStart)
            ->whereDate('ap.appointment_date', '<=', $previousEnd)
            ->count();

        $donationRecordCurrent = $this->dashboardTableHasColumns('donation_records', ['donation_date'])
            ? (int) DB::table('donation_records as dr')
                ->whereDate('dr.donation_date', '>=', $currentStart)
                ->whereDate('dr.donation_date', '<=', $currentEnd)
                ->count()
            : 0;
        $donationRecordPrevious = $this->dashboardTableHasColumns('donation_records', ['donation_date'])
            ? (int) DB::table('donation_records as dr')
                ->whereDate('dr.donation_date', '>=', $previousStart)
                ->whereDate('dr.donation_date', '<=', $previousEnd)
                ->count()
            : 0;

        return [
            'total_donors' => [
                'value' => number_format($donorTotal),
                'change' => $this->dashboardMonthlyChangeLabel($donorCurrent, $donorPrevious),
            ],
            'successful_donations' => [
                'value' => number_format($successfulDonationTotal),
                'change' => $this->dashboardMonthlyChangeLabel($successfulDonationCurrent, $successfulDonationPrevious),
            ],
            'upcoming_appointments' => [
                'value' => number_format($upcomingAppointmentTotal),
                'change' => $this->dashboardMonthlyChangeLabel($appointmentCurrent, $appointmentPrevious),
            ],
            'donation_records' => [
                'value' => number_format($donationRecordTotal),
                'change' => $this->dashboardMonthlyChangeLabel($donationRecordCurrent, $donationRecordPrevious),
            ],
            'operational' => [
                'verified_donors' => $verifiedDonorTotal,
                'pending_verification' => $pendingVerificationTotal,
                'eligible_donors' => $eligibleDonorTotal,
                'open_requests' => $openBloodRequestTotal,
                'emergency_requests' => $emergencyBloodRequestTotal,
                'low_stock' => $lowStockTotal,
                'out_of_stock' => $outOfStockTotal,
            ],
        ];
    }

    /**
     * Count unread admin notifications for the dashboard badge.
     *
     * Donor notifications belong to the donor's Alerts page. The admin bell
     * and Notification Center are backed by admin_notifications, so the two
     * inboxes must not share a count.
     */
    private function dashboardUnreadNotificationCount(): int
    {
        if (! $this->dashboardTableHasColumns('admin_notifications', ['admin_notification_id', 'is_read'])) {
            return 0;
        }

        $query = DB::table('admin_notifications')
            ->where(function ($builder): void {
                $builder->where('is_read', 0)->orWhereNull('is_read');
            });

        if ($this->dashboardTableHasColumns('admin_notifications', ['deleted_at'])) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }

    /**
     * Resolve the latest unread admin notification for the dashboard banner.
     *
     * @return array<string, mixed>|null
     */
    private function dashboardLatestUnreadAdminNotification(): ?array
    {
        if (! $this->dashboardTableHasColumns('admin_notifications', ['admin_notification_id', 'title', 'message', 'is_read'])) {
            return null;
        }

        $query = DB::table('admin_notifications')
            ->where(function ($builder): void {
                $builder->where('is_read', 0)->orWhereNull('is_read');
            })
            ->orderByDesc('created_at')
            ->orderByDesc('admin_notification_id');

        if ($this->dashboardTableHasColumns('admin_notifications', ['deleted_at'])) {
            $query->whereNull('deleted_at');
        }

        $notification = $query->first();

        if (! $notification) {
            return null;
        }

        return [
            'title' => trim((string) ($notification->title ?? 'Notification')) ?: 'Notification',
            'message' => trim((string) ($notification->message ?? '')),
            'type' => trim((string) ($notification->notification_type ?? 'system')) ?: 'system',
            'created_at' => $notification->created_at
                ? Carbon::parse($notification->created_at)->format('M j, Y g:i A')
                : null,
            'url' => route('admin.notification-center'),
        ];
    }

    /**
     * Build current, previous month date ranges.
     *
     * @return array<int, string>
     */
    private function dashboardMonthRanges(): array
    {
        $now = Carbon::now();

        return [
            $now->copy()->startOfMonth()->toDateString(),
            $now->copy()->endOfMonth()->toDateString(),
            $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Format the monthly change label used on summary cards.
     */
    private function dashboardMonthlyChangeLabel(int $current, int $previous): string
    {
        if ($previous === 0 && $current === 0) {
            return '0% this month';
        }

        if ($previous === 0) {
            return 'New this month';
        }

        if ($current === $previous) {
            return 'No change this month';
        }

        $change = (($current - $previous) / $previous) * 100;
        $prefix = $change > 0 ? '+' : '';

        return $prefix.$this->dashboardFormatPercent($change).'% this month';
    }

    /**
     * Format a percentage with one optional decimal place.
     */
    private function dashboardFormatPercent(float $value): string
    {
        $formatted = number_format(round($value, 1), 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Base query for dashboard successful donations.
     */
    private function dashboardSuccessfulDonationQuery()
    {
        if (! $this->dashboardTableHasColumns('donation_records', ['donation_date'])) {
            return DB::query()->fromRaw('(select null as donation_id, null as donation_date where 1 = 0) as dr');
        }

        $query = DB::table('donation_records as dr');

        if ($this->dashboardTableHasColumns('donation_records', ['remarks'])) {
            return $query->whereRaw('('.$this->donationRecordStatusExpression('dr').') = ?', ['completed']);
        }

        return $query->whereNotNull('dr.donation_date');
    }

    /**
     * Base query for appointments that are pending/approved/scheduled.
     */
    private function dashboardSchedulableAppointmentQuery()
    {
        if (! $this->dashboardTableHasColumns('appointments', ['appointment_date', 'status'])) {
            return DB::query()->fromRaw('(select null as appointment_id, null as appointment_date, null as status where 1 = 0) as ap');
        }

        $statusExpression = $this->appointmentStatusExpression('ap');

        return DB::table('appointments as ap')
            ->whereRaw('('.$statusExpression.') in (?, ?)', ['pending', 'confirmed']);
    }

    /**
     * Base query for upcoming appointments.
     */
    private function dashboardUpcomingAppointmentQuery()
    {
        return $this->dashboardSchedulableAppointmentQuery()
            ->whereDate('ap.appointment_date', '>=', Carbon::today()->toDateString());
    }

    /**
     * Build successful donation counts by month for the current year.
     *
     * @return array<string, mixed>
     */
    private function buildDashboardMonthlyDonations(): array
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $values = array_fill(0, 12, 0);

        if ($this->dashboardTableHasColumns('donation_records', ['donation_date'])) {
            $rows = $this->dashboardSuccessfulDonationQuery()
                ->whereYear('dr.donation_date', Carbon::now()->year)
                ->selectRaw('MONTH(dr.donation_date) as month_number, COUNT(*) as total')
                ->groupByRaw('MONTH(dr.donation_date)')
                ->pluck('total', 'month_number');

            foreach ($rows as $month => $total) {
                $monthIndex = (int) $month - 1;
                if ($monthIndex >= 0 && $monthIndex < 12) {
                    $values[$monthIndex] = (int) $total;
                }
            }
        }

        $maxValue = max($values);
        [$maxY, $stepY] = $this->dashboardChartScale($maxValue);

        return [
            'labels' => $labels,
            'values' => $values,
            'maxY' => $maxY,
            'stepY' => $stepY,
        ];
    }

    /**
     * Resolve a readable chart scale for the dashboard line chart.
     *
     * @return array<int, int>
     */
    private function dashboardChartScale(int $maxValue): array
    {
        if ($maxValue <= 0) {
            return [5, 1];
        }

        $roughStep = max(1, (int) ceil($maxValue / 5));
        $magnitude = 10 ** max(0, strlen((string) $roughStep) - 1);
        $normalized = $roughStep / $magnitude;

        if ($normalized <= 1) {
            $niceStep = 1 * $magnitude;
        } elseif ($normalized <= 2) {
            $niceStep = 2 * $magnitude;
        } elseif ($normalized <= 5) {
            $niceStep = 5 * $magnitude;
        } else {
            $niceStep = 10 * $magnitude;
        }

        $maxY = (int) (ceil($maxValue / $niceStep) * $niceStep);

        return [max($niceStep, $maxY), $niceStep];
    }

    /**
     * Build donor blood type distribution chart data.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildDashboardBloodTypeDistribution(): array
    {
        if (! $this->dashboardTableHasColumns('donors', ['donor_id', 'blood_type_id'])
            || ! $this->dashboardTableHasColumns('blood_types', ['blood_type_id', 'blood_type'])) {
            return [];
        }

        $rows = DB::table('blood_types as bt')
            ->leftJoin('donors as d', 'd.blood_type_id', '=', 'bt.blood_type_id')
            ->whereNotNull('bt.blood_type')
            ->where('bt.blood_type', '!=', '')
            ->select('bt.blood_type', DB::raw('COUNT(d.donor_id) as donor_count'))
            ->groupBy('bt.blood_type')
            ->orderBy('bt.blood_type')
            ->get();

        $totalDonorsWithType = (int) $rows->sum('donor_count');
        if ($totalDonorsWithType <= 0) {
            return [];
        }

        $palette = ['#b60c0c', '#5a0000', '#e83333', '#f07070', '#ffd0d0', '#8f1010', '#d64545', '#ff9a9a'];

        return $rows
            ->filter(fn (object $row): bool => (int) $row->donor_count > 0)
            ->values()
            ->map(function (object $row, int $index) use ($totalDonorsWithType, $palette): array {
                return [
                    'label' => (string) $row->blood_type,
                    'value' => round(((int) $row->donor_count) / $totalDonorsWithType, 4),
                    'count' => (int) $row->donor_count,
                    'color' => $palette[$index % count($palette)],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build a mixed feed of recent dashboard activities.
     *
     * @return array<int, array<string, string>>
     */
    private function buildDashboardRecentActivities(): array
    {
        $activities = collect();

        if ($this->dashboardTableHasColumns('audit_logs', ['actor_name', 'description', 'action_type', 'created_at'])) {
            DB::table('audit_logs')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['actor_name', 'description', 'action_type', 'created_at'])
                ->each(function (object $row) use ($activities): void {
                    $activities->push($this->dashboardActivityItem(
                        (string) ($row->actor_name ?: 'System'),
                        (string) ($row->description ?: Str::headline((string) ($row->action_type ?? 'Activity'))),
                        (string) ($row->created_at ?? ''),
                        $this->dashboardActivityTone((string) ($row->action_type ?? ''))
                    ));
                });
        }

        if ($this->dashboardTableHasColumns('donors', ['first_name', 'last_name', 'date_registered'])) {
            DB::table('donors')
                ->orderByDesc('date_registered')
                ->limit(5)
                ->get(['first_name', 'last_name', 'date_registered'])
                ->each(function (object $row) use ($activities): void {
                    $activities->push($this->dashboardActivityItem(
                        $this->dashboardDonorName($row),
                        'Registration Complete',
                        (string) ($row->date_registered ?? ''),
                        'red'
                    ));
                });
        }

        if ($this->dashboardTableHasColumns('donation_records', ['donation_date', 'donor_id'])
            && $this->dashboardTableHasColumns('donors', ['donor_id', 'first_name', 'last_name'])) {
            $this->dashboardSuccessfulDonationQuery()
                ->leftJoin('donors as d', 'd.donor_id', '=', 'dr.donor_id')
                ->orderByDesc('dr.donation_date')
                ->limit(5)
                ->get(['d.first_name', 'd.last_name', 'dr.donation_date'])
                ->each(function (object $row) use ($activities): void {
                    $activities->push($this->dashboardActivityItem(
                        $this->dashboardDonorName($row),
                        'Completed Donation',
                        (string) ($row->donation_date ?? ''),
                        'green'
                    ));
                });
        }

        if ($this->dashboardTableHasColumns('appointments', ['donor_id', 'status', 'created_at'])
            && $this->dashboardTableHasColumns('donors', ['donor_id', 'first_name', 'last_name'])) {
            DB::table('appointments as ap')
                ->leftJoin('donors as d', 'd.donor_id', '=', 'ap.donor_id')
                ->orderByDesc('ap.created_at')
                ->limit(5)
                ->get(['d.first_name', 'd.last_name', 'ap.status', 'ap.created_at'])
                ->each(function (object $row) use ($activities): void {
                    $status = $this->normalizeAppointmentStatusValue((string) ($row->status ?? ''));
                    $activities->push($this->dashboardActivityItem(
                        $this->dashboardDonorName($row),
                        $status === 'cancelled' ? 'Cancelled Appointment' : 'Booked Appointment',
                        (string) ($row->created_at ?? ''),
                        $status === 'cancelled' ? 'gold' : 'blue'
                    ));
                });
        }

        if ($this->dashboardTableHasColumns('eligibility_status', ['donor_id', 'status', 'eligibility_id'])
            && $this->dashboardTableHasColumns('donors', ['donor_id', 'first_name', 'last_name'])) {
            DB::table('eligibility_status as es')
                ->leftJoin('donors as d', 'd.donor_id', '=', 'es.donor_id')
                ->orderByDesc('es.eligibility_id')
                ->limit(5)
                ->get(['d.first_name', 'd.last_name', 'es.status', 'es.eligibility_id'])
                ->each(function (object $row) use ($activities): void {
                    $activities->push($this->dashboardActivityItem(
                        $this->dashboardDonorName($row),
                        'Eligibility '.Str::headline((string) ($row->status ?? 'Update')),
                        '',
                        'gold',
                        (int) ($row->eligibility_id ?? 0)
                    ));
                });
        }

        return $activities
            ->filter(fn (array $item): bool => $item['name'] !== '' || $item['action'] !== '')
            ->sortByDesc('sort_value')
            ->take(5)
            ->map(fn (array $item): array => [
                'name' => $item['name'],
                'action' => $item['action'],
                'time' => $item['time'],
                'tone' => $item['tone'],
            ])
            ->values()
            ->all();
    }

    /**
     * Build pending approval rows for the dashboard.
     *
     * @return array<int, array<string, string>>
     */
    private function buildDashboardPendingApprovals(): array
    {
        $approvals = collect();

        if ($this->dashboardTableHasColumns('eligibility_status', ['donor_id', 'status', 'eligibility_id'])
            && $this->dashboardTableHasColumns('donors', ['donor_id', 'first_name', 'last_name'])) {
            DB::table('eligibility_status as es')
                ->leftJoin('donors as d', 'd.donor_id', '=', 'es.donor_id')
                ->where(function ($query): void {
                    $query->whereNull('es.status')
                        ->orWhereIn('es.status', ['for_review', 'for review', 'pending']);
                })
                ->orderByDesc('es.eligibility_id')
                ->limit(5)
                ->get(['d.first_name', 'd.last_name'])
                ->each(function (object $row) use ($approvals): void {
                    $approvals->push([
                        'name' => $this->dashboardDonorName($row),
                        'type' => 'Eligibility Review',
                        'approve_url' => route('admin.eligibility.index'),
                        'review_url' => route('admin.eligibility.index'),
                    ]);
                });
        }

        if ($approvals->count() < 5
            && $this->dashboardTableHasColumns('appointments', ['donor_id', 'status', 'appointment_date'])
            && $this->dashboardTableHasColumns('donors', ['donor_id', 'first_name', 'last_name'])) {
            $needed = 5 - $approvals->count();
            $statusExpression = $this->appointmentStatusExpression('ap');

            DB::table('appointments as ap')
                ->leftJoin('donors as d', 'd.donor_id', '=', 'ap.donor_id')
                ->whereRaw('('.$statusExpression.') = ?', ['pending'])
                ->orderBy('ap.appointment_date')
                ->limit($needed)
                ->get(['d.first_name', 'd.last_name'])
                ->each(function (object $row) use ($approvals): void {
                    $approvals->push([
                        'name' => $this->dashboardDonorName($row),
                        'type' => 'Pending Appointment',
                        'approve_url' => route('admin.appointments'),
                        'review_url' => route('admin.appointments'),
                    ]);
                });
        }

        return $approvals->values()->all();
    }

    /**
     * Normalize a dashboard activity row.
     *
     * @return array<string, string|int>
     */
    private function dashboardActivityItem(string $name, string $action, string $timestamp, string $tone, int $fallbackSort = 0): array
    {
        $parsed = null;

        try {
            $parsed = trim($timestamp) !== '' ? Carbon::parse($timestamp) : null;
        } catch (Throwable) {
            $parsed = null;
        }

        return [
            'name' => trim($name) !== '' ? trim($name) : 'Unknown Donor',
            'action' => trim($action) !== '' ? trim($action) : 'Activity',
            'time' => $parsed ? $parsed->diffForHumans() : 'Recently',
            'tone' => in_array($tone, ['green', 'blue', 'gold', 'red'], true) ? $tone : 'blue',
            'sort_value' => $parsed ? $parsed->timestamp : $fallbackSort,
        ];
    }

    /**
     * Resolve dashboard activity color tone from an action name.
     */
    private function dashboardActivityTone(string $actionType): string
    {
        $action = Str::lower($actionType);

        if (Str::contains($action, ['delete', 'cancel', 'reject', 'decline', 'fail'])) {
            return 'red';
        }

        if (Str::contains($action, ['complete', 'approve', 'success'])) {
            return 'green';
        }

        if (Str::contains($action, ['update', 'review', 'security'])) {
            return 'gold';
        }

        return 'blue';
    }

    /**
     * Build a readable donor name from joined donor columns.
     */
    private function dashboardDonorName(object $row): string
    {
        $name = trim((string) ($row->first_name ?? '').' '.(string) ($row->last_name ?? ''));

        return $name !== '' ? $name : 'Unknown Donor';
    }

    /**
     * Normalize donation record row payload for donation records front-end.
     *
     * @return array<string, mixed>
     */
    private function transformDonationRecordRow(object $entry): array
    {
        $donorName = trim((string) ($entry->first_name ?? '').' '.(string) ($entry->last_name ?? ''));
        if ($donorName === '') {
            $donorName = 'Unknown Donor';
        }

        $status = Str::lower(trim((string) ($entry->derived_status ?? 'pending')));
        if (! in_array($status, ['completed', 'pending', 'deferred'], true)) {
            $status = 'pending';
        }

        $unitsRaw = $entry->blood_units ?? null;
        $units = is_numeric($unitsRaw) ? (int) $unitsRaw : null;
        $volumeMl = $units !== null ? $units * 450 : null;

        return [
            'donation_id' => (int) ($entry->donation_id ?? 0),
            'record_code' => 'DR'.str_pad((string) ((int) ($entry->donation_id ?? 0)), 3, '0', STR_PAD_LEFT),
            'donor_id' => (int) ($entry->donor_id ?? 0),
            'donor_name' => $donorName,
            'blood_type' => trim((string) ($entry->blood_type ?? '')),
            'donation_date' => ! empty($entry->donation_date) ? (string) $entry->donation_date : null,
            'center_label' => trim((string) ($entry->center_label ?? '')) !== ''
                ? trim((string) $entry->center_label)
                : 'N/A',
            'volume_ml' => $volumeMl,
            'status' => $status,
            'next_eligible_date' => ! empty($entry->next_eligible_date) ? (string) $entry->next_eligible_date : null,
        ];
    }

    /**
     * Build the base donor directory query used by admin user management page.
     */
    private function userManagementDonorQuery()
    {
        $statusExpression = $this->userManagementStatusExpression();
        $verificationStatus = Schema::hasColumn('donors', 'verification_status')
            ? 'd.verification_status'
            : DB::raw("'unverified' as verification_status");
        $accountActive = Schema::hasColumn('donors', 'is_active')
            ? 'd.is_active'
            : DB::raw('1 as is_active');

        return DB::table('donors as d')
            ->leftJoinSub($this->appointmentLatestDonorAuthQuery(), 'da_latest', function ($join): void {
                $join->on('da_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('donor_authentication as da', 'da.auth_id', '=', 'da_latest.latest_auth_id')
            ->leftJoin('blood_types as bt', 'bt.blood_type_id', '=', 'd.blood_type_id')
            ->leftJoinSub($this->userManagementDonationAggregateQuery(), 'drs', function ($join): void {
                $join->on('drs.donor_id', '=', 'd.donor_id');
            })
            ->leftJoinSub($this->userManagementLatestEligibilityQuery(), 'es_latest', function ($join): void {
                $join->on('es_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('eligibility_status as es', 'es.eligibility_id', '=', 'es_latest.latest_eligibility_id')
            ->select([
                'd.donor_id',
                'd.first_name',
                'd.last_name',
                'd.contact_number',
                $verificationStatus,
                $accountActive,
                'da.email',
                'bt.blood_type',
                DB::raw('COALESCE(drs.total_donations, 0) as total_donations'),
                DB::raw('drs.last_donation_date as last_donation_date'),
            ])
            ->selectRaw('('.$statusExpression.') as derived_status');
    }

    /**
     * Aggregate donation totals and latest donation date per donor.
     */
    private function userManagementDonationAggregateQuery()
    {
        // Some older local installations predate the donation_records table.
        // Keep the donor directory usable in that state and report zero
        // donation totals instead of failing the entire admin page.
        if (! Schema::hasTable('donation_records')) {
            return DB::query()
                ->fromRaw('(select NULL as donor_id, 0 as total_donations, NULL as last_donation_date where 1 = 0) as empty_donations')
                ->select([
                    'donor_id',
                    'total_donations',
                    'last_donation_date',
                ]);
        }

        return DB::table('donation_records')
            ->select([
                'donor_id',
                DB::raw('COUNT(*) as total_donations'),
                DB::raw('MAX(donation_date) as last_donation_date'),
            ])
            ->groupBy('donor_id');
    }

    /**
     * Prevent spreadsheet applications from treating exported user text as
     * a formula while preserving normal CSV values.
     */
    private function safeCsvCell(mixed $value): string|int|float
    {
        if (! is_string($value)) {
            return is_numeric($value) ? $value : (string) $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Resolve latest eligibility row per donor.
     */
    private function userManagementLatestEligibilityQuery()
    {
        return DB::table('eligibility_status')
            ->select([
                'donor_id',
                DB::raw('MAX(eligibility_id) as latest_eligibility_id'),
            ])
            ->groupBy('donor_id');
    }

    /**
     * Build SQL expression to derive donor eligibility label.
     */
    private function userManagementStatusExpression(): string
    {
        $waitingPeriodCutoff = DB::connection()->getDriverName() === 'sqlite'
            ? "date('now', '-56 days')"
            : 'DATE_SUB(CURDATE(), INTERVAL 56 DAY)';

        return "CASE
            WHEN LOWER(COALESCE(es.status, '')) IN ('eligible', 'qualified', 'ready', 'approved') THEN 'eligible'
            WHEN LOWER(COALESCE(es.status, '')) IN ('not eligible', 'not_eligible', 'temporary deferred', 'temporary_deferred', 'for review', 'for_review', 'deferred', 'ineligible', 'declined') THEN 'not_eligible'
            WHEN drs.last_donation_date IS NULL THEN 'eligible'
            WHEN drs.last_donation_date <= {$waitingPeriodCutoff} THEN 'eligible'
            ELSE 'not_eligible'
        END";
    }

    /**
     * Build the donor detail query used by admin user actions.
     */
    private function userManagementDonorDetailQuery()
    {
        $statusExpression = $this->userManagementStatusExpression();
        $verificationStatus = Schema::hasColumn('donors', 'verification_status')
            ? 'd.verification_status'
            : DB::raw("'unverified' as verification_status");
        $bloodTypeStatus = Schema::hasColumn('donors', 'blood_type_status')
            ? 'd.blood_type_status'
            : DB::raw("'not_yet_determined' as blood_type_status");
        $bloodTypeVerifiedAt = Schema::hasColumn('donors', 'blood_type_verified_at')
            ? 'd.blood_type_verified_at'
            : DB::raw('NULL as blood_type_verified_at');
        $accountActive = Schema::hasColumn('donors', 'is_active')
            ? 'd.is_active'
            : DB::raw('1 as is_active');

        return DB::table('donors as d')
            ->leftJoinSub($this->appointmentLatestDonorAuthQuery(), 'da_latest', function ($join): void {
                $join->on('da_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('donor_authentication as da', 'da.auth_id', '=', 'da_latest.latest_auth_id')
            ->leftJoin('blood_types as bt', 'bt.blood_type_id', '=', 'd.blood_type_id')
            ->leftJoin('locations as l', 'l.location_id', '=', 'd.location_id')
            ->leftJoinSub($this->userManagementDonationAggregateQuery(), 'drs', function ($join): void {
                $join->on('drs.donor_id', '=', 'd.donor_id');
            })
            ->leftJoinSub($this->userManagementLatestEligibilityQuery(), 'es_latest', function ($join): void {
                $join->on('es_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('eligibility_status as es', 'es.eligibility_id', '=', 'es_latest.latest_eligibility_id')
            ->select([
                'd.donor_id',
                'd.first_name',
                'd.last_name',
                'd.gender',
                'd.birthdate',
                'd.contact_number',
                'd.blood_type_id',
                $bloodTypeStatus,
                $bloodTypeVerifiedAt,
                'd.location_id',
                'd.date_registered',
                $verificationStatus,
                $accountActive,
                'da.auth_id',
                'da.email',
                'da.created_at as auth_created_at',
                'bt.blood_type',
                'l.street_address',
                'l.barangay_name',
                'l.city',
                'l.province',
                'l.latitude',
                'l.longitude',
                'es.eligibility_id',
                'es.status as latest_eligibility_status',
                'es.next_eligible_date',
                DB::raw('COALESCE(drs.total_donations, 0) as total_donations'),
                DB::raw('drs.last_donation_date as last_donation_date'),
                DB::raw('NULL as updated_at'),
            ])
            ->selectRaw('('.$statusExpression.') as derived_status');
    }

    /**
     * Resolve distinct blood type filter values for admin users page.
     *
     * @return array<int, string>
     */
    private function userManagementBloodTypeOptions(): array
    {
        return DB::table('blood_types')
            ->whereNotNull('blood_type')
            ->where('blood_type', '!=', '')
            ->orderBy('blood_type')
            ->pluck('blood_type')
            ->values()
            ->all();
    }

    /**
     * Resolve blood type options for user management forms.
     *
     * @return array<int, array<string, mixed>>
     */
    private function userManagementBloodTypeFormOptions(): array
    {
        return BloodType::query()
            ->whereIn('blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])
            ->orderBy('blood_type')
            ->get(['blood_type_id', 'blood_type'])
            ->map(function (BloodType $bloodType): array {
                return [
                    'id' => (int) $bloodType->blood_type_id,
                    'label' => trim((string) $bloodType->blood_type),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Resolve status options for user management forms.
     *
     * @return array<int, array<string, string>>
     */
    private function userManagementStatusOptions(): array
    {
        return [
            ['value' => 'eligible', 'label' => 'Eligible'],
            ['value' => 'not_eligible', 'label' => 'Not Eligible'],
        ];
    }

    /**
     * Fetch a single donor payload for admin user management.
     *
     * @return array<string, mixed>|null
     */
    private function getUserManagementDonorDetail(int $donorId): ?array
    {
        $row = $this->userManagementDonorDetailQuery()
            ->where('d.donor_id', $donorId)
            ->first();

        return $row ? $this->transformUserManagementDonorDetail($row) : null;
    }

    /**
     * Normalize donor row payload for admin user management front-end.
     *
     * @return array<string, mixed>
     */
    private function transformUserManagementDonor(object $donor): array
    {
        $fullName = trim((string) ($donor->first_name ?? '').' '.(string) ($donor->last_name ?? ''));
        if ($fullName === '') {
            $fullName = 'Donor #'.(int) ($donor->donor_id ?? 0);
        }

        $status = Str::lower(trim((string) ($donor->derived_status ?? 'eligible')));
        if (! in_array($status, ['eligible', 'not_eligible'], true)) {
            $status = 'eligible';
        }

        return [
            'donor_id' => (int) ($donor->donor_id ?? 0),
            'donor_code' => 'D'.str_pad((string) ((int) ($donor->donor_id ?? 0)), 3, '0', STR_PAD_LEFT),
            'full_name' => $fullName,
            'email' => trim((string) ($donor->email ?? '')),
            'blood_type' => trim((string) ($donor->blood_type ?? '')),
            'contact_number' => trim((string) ($donor->contact_number ?? '')),
            'last_donation_date' => ! empty($donor->last_donation_date)
                ? (string) $donor->last_donation_date
                : null,
            'eligibility_status' => $status,
            'total_donations' => (int) ($donor->total_donations ?? 0),
            'verification_status' => $this->normalizeDonorVerificationStatus($donor->verification_status ?? null),
            'is_active' => (bool) ($donor->is_active ?? true),
        ];
    }

    /**
     * Normalize donor detail payload for admin user actions.
     *
     * @return array<string, mixed>
     */
    private function transformUserManagementDonorDetail(object $donor): array
    {
        $fullName = trim((string) ($donor->first_name ?? '').' '.(string) ($donor->last_name ?? ''));
        if ($fullName === '') {
            $fullName = 'Donor #'.(int) ($donor->donor_id ?? 0);
        }

        $status = $this->normalizeUserManagementStatusValue((string) ($donor->derived_status ?? 'eligible'));

        return [
            'donor_id' => (int) ($donor->donor_id ?? 0),
            'donor_code' => 'D'.str_pad((string) ((int) ($donor->donor_id ?? 0)), 3, '0', STR_PAD_LEFT),
            'auth_id' => isset($donor->auth_id) ? (int) $donor->auth_id : null,
            'eligibility_id' => isset($donor->eligibility_id) ? (int) $donor->eligibility_id : null,
            'first_name' => trim((string) ($donor->first_name ?? '')),
            'last_name' => trim((string) ($donor->last_name ?? '')),
            'full_name' => $fullName,
            'email' => trim((string) ($donor->email ?? '')),
            'contact_number' => trim((string) ($donor->contact_number ?? '')),
            'gender' => $this->userManagementNullableString($donor->gender ?? null),
            'birthdate' => ! empty($donor->birthdate) ? (string) $donor->birthdate : null,
            'blood_type_id' => isset($donor->blood_type_id) ? (int) $donor->blood_type_id : null,
            'blood_type' => trim((string) ($donor->blood_type ?? '')),
            'blood_type_status' => Str::lower(trim((string) ($donor->blood_type_status ?? 'not_yet_determined'))),
            'verification_status' => $this->normalizeDonorVerificationStatus($donor->verification_status ?? null),
            'is_active' => (bool) ($donor->is_active ?? true),
            'blood_type_verified_at' => ! empty($donor->blood_type_verified_at) ? (string) $donor->blood_type_verified_at : null,
            'location_id' => isset($donor->location_id) ? (int) $donor->location_id : null,
            'street_address' => $this->userManagementNullableString($donor->street_address ?? null),
            'barangay_name' => $this->userManagementNullableString($donor->barangay_name ?? null),
            'city' => $this->userManagementNullableString($donor->city ?? null),
            'province' => $this->userManagementNullableString($donor->province ?? null),
            'latitude' => is_numeric($donor->latitude ?? null) ? (float) $donor->latitude : null,
            'longitude' => is_numeric($donor->longitude ?? null) ? (float) $donor->longitude : null,
            'full_address' => $this->userManagementFullAddress([
                $donor->street_address ?? null,
                $donor->barangay_name ?? null,
                $donor->city ?? null,
                $donor->province ?? null,
            ]),
            'eligibility_status' => $status,
            'eligibility_status_raw' => $this->userManagementNullableString($donor->latest_eligibility_status ?? null),
            'last_donation_date' => ! empty($donor->last_donation_date) ? (string) $donor->last_donation_date : null,
            'next_eligible_date' => ! empty($donor->next_eligible_date) ? (string) $donor->next_eligible_date : null,
            'total_donations' => (int) ($donor->total_donations ?? 0),
            'date_registered' => ! empty($donor->date_registered) ? (string) $donor->date_registered : null,
            'auth_created_at' => ! empty($donor->auth_created_at) ? (string) $donor->auth_created_at : null,
            'updated_at' => ! empty($donor->updated_at) ? (string) $donor->updated_at : null,
        ];
    }

    /**
     * Normalize user management status into front-end supported values.
     */
    private function normalizeUserManagementStatusValue(?string $status): string
    {
        return Str::lower(trim((string) $status)) === 'not_eligible'
            ? 'not_eligible'
            : 'eligible';
    }

    /**
     * Keep identity-verification labels limited to the statuses supported by
     * the donor verification workflow.
     */
    private function normalizeDonorVerificationStatus(mixed $status): string
    {
        $normalized = Str::lower(trim((string) ($status ?? 'unverified')));

        return in_array($normalized, ['unverified', 'pending', 'verified', 'rejected'], true)
            ? $normalized
            : 'unverified';
    }

    /**
     * Map front-end status values into stored eligibility status values.
     */
    private function userManagementStatusDatabaseValue(string $status): string
    {
        return $this->normalizeUserManagementStatusValue($status) === 'not_eligible'
            ? 'not_eligible'
            : 'eligible';
    }

    /**
     * Build a location payload from validated user management input.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string|null>
     */
    private function userManagementLocationPayload(array $validated): array
    {
        return [
            'street_address' => $this->userManagementNullableString($validated['street_address'] ?? null),
            'barangay_name' => $this->userManagementNullableString($validated['barangay_name'] ?? null),
            'city' => $this->userManagementNullableString($validated['city'] ?? null),
            'province' => $this->userManagementNullableString($validated['province'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, float>|false|null
     */
    private function userManagementManualCoordinates(array $validated): array|false|null
    {
        $hasLatitude = array_key_exists('latitude', $validated) && $validated['latitude'] !== null && $validated['latitude'] !== '';
        $hasLongitude = array_key_exists('longitude', $validated) && $validated['longitude'] !== null && $validated['longitude'] !== '';

        if (! $hasLatitude && ! $hasLongitude) {
            return null;
        }

        if (! $hasLatitude || ! $hasLongitude) {
            return false;
        }

        return [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
        ];
    }

    /**
     * Determine whether a location payload contains meaningful data.
     *
     * @param  array<string, string|null>  $payload
     */
    private function userManagementLocationPayloadHasValue(array $payload): bool
    {
        foreach ($payload as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether an existing location already matches a payload.
     *
     * @param  array<string, string|null>  $payload
     */
    private function userManagementLocationMatches(?Location $location, array $payload): bool
    {
        if (! $location) {
            return false;
        }

        foreach ($payload as $field => $value) {
            if ($this->userManagementNullableString($location->{$field} ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build a user-friendly full address label from location parts.
     *
     * @param  array<int, mixed>  $parts
     */
    private function userManagementFullAddress(array $parts): ?string
    {
        $segments = [];

        foreach ($parts as $part) {
            $value = $this->userManagementNullableString($part);
            if ($value !== null) {
                $segments[] = $value;
            }
        }

        return $segments === [] ? null : implode(', ', $segments);
    }

    /**
     * Normalize blank strings into null values.
     */
    private function userManagementNullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Extract changed donor fields for audit metadata.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array<string, mixed>>
     */
    private function userManagementChangedFields(array $before, array $after): array
    {
        $changes = [];
        $trackedFields = [
            'first_name',
            'last_name',
            'email',
            'contact_number',
            'gender',
            'birthdate',
            'blood_type_id',
            'blood_type',
            'street_address',
            'barangay_name',
            'city',
            'province',
            'eligibility_status',
        ];

        foreach ($trackedFields as $field) {
            $beforeValue = $before[$field] ?? null;
            $afterValue = $after[$field] ?? null;

            if ((string) $beforeValue !== (string) $afterValue) {
                $changes[$field] = [
                    'from' => $beforeValue,
                    'to' => $afterValue,
                ];
            }
        }

        return $changes;
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
                && ! empty($admin->two_factor_confirmed_at);
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

    private function currentAdminId(Request $request): int
    {
        $adminId = $request->session()->get('admin_id');

        if (is_numeric($adminId) && (int) $adminId > 0) {
            return (int) $adminId;
        }

        throw ValidationException::withMessages([
            'admin' => 'An authenticated admin session is required.',
        ]);
    }

    /**
     * Create a donor-facing notification when notifications table is available.
     */
    private function createDonorNotification(?int $donorId, string $type, string $message): void
    {
        if ($donorId === null || $donorId <= 0) {
            return;
        }

        if (! Schema::hasTable('notifications')) {
            return;
        }

        try {
            DB::table('notifications')->insert([
                'donor_id' => $donorId,
                'message' => $message,
                'notification_type' => $type,
                'is_read' => 0,
                'created_at' => now(),
                'push_sent' => 0,
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to create donor notification.', [
                'donor_id' => $donorId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Persist appointment actions to audit logs table when available.
     */
    private function logAppointmentAudit(
        Request $request,
        string $actionType,
        string $description,
        ?int $appointmentId = null,
        array $metadata = [],
        string $result = 'success'
    ): void {
        try {
            $actorName = trim((string) ($request->session()->get('admin_full_name') ?: $request->session()->get('admin_username') ?: 'Admin'));
            $actorRole = ucfirst($this->normalizeRole((string) $request->session()->get('admin_role', 'admin')));

            if (! Schema::hasTable('audit_logs')) {
                logger()->info('Appointment audit event', [
                    'action_type' => $actionType,
                    'description' => $description,
                    'appointment_id' => $appointmentId,
                    'metadata' => $metadata,
                ]);

                return;
            }

            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'action_type' => $actionType,
                'module_type' => 'appointments',
                'target_table' => 'appointments',
                'target_id' => $appointmentId,
                'description' => $description,
                'ip_address' => $request->ip(),
                'result' => $result,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to persist appointment audit event.', [
                'action_type' => $actionType,
                'appointment_id' => $appointmentId,
                'error' => $exception->getMessage(),
            ]);
        }
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

            if (! Schema::hasTable('audit_logs')) {
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
     * Persist a donor user-management action to audit logs when available.
     */
    private function logUserManagementAudit(
        Request $request,
        string $actionType,
        string $description,
        ?int $targetDonorId = null,
        array $metadata = [],
        string $result = 'success'
    ): void {
        try {
            $actorName = trim((string) ($request->session()->get('admin_full_name') ?: $request->session()->get('admin_username') ?: 'Admin'));
            $actorRole = ucfirst($this->normalizeRole((string) $request->session()->get('admin_role', 'admin')));

            if (! Schema::hasTable('audit_logs')) {
                logger()->info('User management audit event', [
                    'action_type' => $actionType,
                    'description' => $description,
                    'target_donor_id' => $targetDonorId,
                    'metadata' => $metadata,
                ]);

                return;
            }

            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'action_type' => $actionType,
                'module_type' => 'user_management',
                'target_table' => 'donors',
                'target_id' => $targetDonorId,
                'description' => $description,
                'ip_address' => $request->ip(),
                'result' => $result,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to persist user management audit event.', [
                'action_type' => $actionType,
                'description' => $description,
                'target_donor_id' => $targetDonorId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Persist non-security admin settings actions to audit logs when
     * available. Sensitive values such as passwords are never recorded.
     */
    private function logAdminSettingsAudit(
        Request $request,
        string $actionType,
        string $description,
        array $metadata = [],
        string $result = 'success'
    ): void {
        try {
            $actorName = trim((string) ($request->session()->get('admin_full_name') ?: $request->session()->get('admin_username') ?: 'Admin'));
            $actorRole = ucfirst($this->normalizeRole((string) $request->session()->get('admin_role', 'admin')));

            if (! Schema::hasTable('audit_logs')) {
                logger()->info('Admin settings audit event', [
                    'action_type' => $actionType,
                    'description' => $description,
                    'metadata' => $metadata,
                ]);

                return;
            }

            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'action_type' => $actionType,
                'module_type' => 'settings',
                'target_table' => self::SYSTEM_SETTINGS_TABLE,
                'target_id' => null,
                'description' => $description,
                'ip_address' => $request->ip(),
                'result' => $result,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to persist admin settings audit event.', [
                'action_type' => $actionType,
                'description' => $description,
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

            if (! Schema::hasTable('audit_logs')) {
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
        string $actor,
        string $actorRole,
        string $actionType,
        string $moduleType,
        string $result,
        string $userType,
        bool $securityPolicyOnly = false,
        ?string $startDate = null,
        ?string $endDate = null
    ): void {
        if ($searchTerm !== '') {
            $likeTerm = '%'.$searchTerm.'%';

            $query->where(function ($builder) use ($likeTerm): void {
                $builder->where('actor_name', 'like', $likeTerm)
                    ->orWhere('actor_role', 'like', $likeTerm)
                    ->orWhere('action_type', 'like', $likeTerm)
                    ->orWhere('description', 'like', $likeTerm)
                    ->orWhere('module_type', 'like', $likeTerm)
                    ->orWhere('ip_address', 'like', $likeTerm)
                    ->orWhere('target_table', 'like', $likeTerm)
                    ->orWhere('created_at', 'like', $likeTerm);
            });
        }

        if ($actor !== '') {
            $query->where(function ($builder) use ($actor): void {
                $likeTerm = '%'.$actor.'%';
                $builder->where('actor_name', 'like', $likeTerm)
                    ->orWhere('actor_admin_id', $actor);
            });
        }

        if ($actorRole !== '') {
            $query->whereRaw('LOWER(COALESCE(actor_role, \'\')) = ?', [$actorRole]);
        }

        if ($actionType !== '') {
            $query->whereRaw('LOWER(action_type) = ?', [$actionType]);
        }

        if ($moduleType !== '') {
            $query->whereRaw('LOWER(COALESCE(module_type, \'\')) = ?', [$moduleType]);
        }

        if ($result !== '') {
            $query->whereRaw("LOWER(COALESCE(result, 'success')) = ?", [$result]);
        }

        if ($startDate !== null && trim($startDate) !== '') {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== null && trim($endDate) !== '') {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
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
    private function transformAuditLogEntry(object $entry, bool $withDetails = false): array
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

        $data = [
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

        if ($withDetails) {
            $data['actorAdminId'] = $entry->actor_admin_id === null ? null : (int) $entry->actor_admin_id;
            $data['targetTable'] = (string) ($entry->target_table ?? '');
            $data['targetId'] = $entry->target_id === null ? null : (int) $entry->target_id;
            $data['metadata'] = $this->safeAuditMetadata($entry->metadata ?? null);
            $data['metadataText'] = $this->safeAuditMetadataText($entry->metadata ?? null);
        }

        return $data;
    }

    /**
     * Decode audit metadata and redact values that could contain credentials,
     * tokens, personal contact data, uploaded documents, or answer payloads.
     *
     * @return array<string, mixed>|null
     */
    private function safeAuditMetadata(mixed $metadata): ?array
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
        } elseif (is_array($metadata)) {
            $decoded = $metadata;
        } else {
            $decoded = null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        return $this->redactAuditValue($decoded);
    }

    private function safeAuditMetadataText(mixed $metadata): string
    {
        $safe = $this->safeAuditMetadata($metadata);

        return $safe === null ? '' : (string) json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function redactAuditValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/password|token|secret|otp|2fa|two.factor|fcm|session|email|contact|phone|document_path|answer/i', $key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $childKey => $childValue) {
                $redacted[(string) $childKey] = $this->redactAuditValue($childValue, (string) $childKey);
            }

            return $redacted;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return '[redacted]';
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
        if (! $this->supportsRememberMeStorage()) {
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
        if (! Schema::hasTable('admins')) {
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

        if (! is_array($cacheData) || empty($cacheData['token_hash'])) {
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
