<?php

namespace App\Http\Controllers;

use App\Mail\AdminPasswordResetMail;
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
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $actionType = Str::lower(trim((string) ($validated['action_type'] ?? '')));
        $userType = Str::lower(trim((string) ($validated['user_type'] ?? '')));

        $baseQuery = DB::table('audit_logs');
        $this->applyAuditLogFilters($baseQuery, $searchTerm, $actionType, $userType);

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
        ]);

        $searchTerm = trim((string) ($validated['search'] ?? ''));
        $actionType = Str::lower(trim((string) ($validated['action_type'] ?? '')));
        $userType = Str::lower(trim((string) ($validated['user_type'] ?? '')));

        $query = DB::table('audit_logs');
        $this->applyAuditLogFilters($query, $searchTerm, $actionType, $userType);

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
            ->select('admin_id', 'full_name', 'username', 'email', 'role', 'created_at');

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
            ->select('admin_id', 'full_name', 'username', 'email', 'role')
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
            ->select('admin_id', 'full_name', 'username', 'email', 'role')
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
            ->select('admin_id', 'full_name', 'username', 'email', 'role')
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
            ->select('admin_id', 'full_name', 'username', 'email', 'role')
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
            ->select('admin_id', 'full_name', 'username', 'email', 'role')
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
            ->select('admin_id', 'full_name', 'username', 'email', 'role')
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
            ->select('admin_id', 'full_name', 'username', 'email', 'role')
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
     * Build RBAC users payload from admins table.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRbacAdminUsers(): array
    {
        return DB::table('admins')
            ->select('admin_id', 'full_name', 'username', 'email', 'role')
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

        return [
            'id' => (int) $admin->admin_id,
            'name' => $displayName,
            'fullName' => (string) ($admin->full_name ?? ''),
            'username' => (string) ($admin->username ?? ''),
            'email' => (string) ($admin->email ?? ''),
            'roleIds' => [$roleId],
        ];
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
     * Apply shared audit log filters used by list and export endpoints.
     */
    private function applyAuditLogFilters($query, string $searchTerm, string $actionType, string $userType): void
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

        $request->session()->forget(['admin_id', 'admin_username', 'admin_full_name', 'admin_role']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'You have been logged out.');
    }
}
