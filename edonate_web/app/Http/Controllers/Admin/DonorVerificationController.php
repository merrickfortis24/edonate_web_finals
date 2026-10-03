<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use App\Models\DonorVerification;
use App\Services\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DonorVerificationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['', 'pending', 'verified', 'rejected'])],
            'search' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
            'focus' => ['nullable', 'integer', 'min:1'],
        ]);

        $focusId = (int) ($validated['focus'] ?? 0);
        $status = $focusId > 0 ? '' : strtolower(trim((string) ($validated['status'] ?? '')));
        $search = $focusId > 0 ? '' : trim((string) ($validated['search'] ?? ''));

        $query = DB::table('donor_verifications as dv')
            ->leftJoin('donors as d', 'd.donor_id', '=', 'dv.donor_id')
            ->leftJoin('admins as reviewer', 'reviewer.admin_id', '=', 'dv.reviewed_by_admin_id');

        $query = $this->joinLatestDonorAuth($query);

        if ($focusId > 0) {
            $query->where('dv.verification_id', $focusId);
        } elseif ($status !== '') {
            $query->where('dv.status', $status);
        }

        if ($focusId === 0 && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->whereRaw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) LIKE ?", [$like])
                    ->orWhere('d.contact_number', 'like', $like)
                    ->orWhere('da.email', 'like', $like)
                    ->orWhere('dv.verification_id', 'like', $like)
                    ->orWhere('dv.donor_id', 'like', $like);
            });
        }

        $select = [
            'dv.verification_id',
            'dv.donor_id',
            'dv.document_type',
            'dv.document_path',
            'dv.document_back_path',
            'dv.status',
            'dv.rejection_reason',
            'dv.reviewed_by_admin_id',
            'dv.reviewed_at',
            'dv.created_at',
            'dv.updated_at',
            DB::raw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) AS donor_name"),
            'd.donor_id as linked_donor_id',
            'd.contact_number',
            'd.verification_status',
            DB::raw("COALESCE(da.email, '') AS donor_email"),
            DB::raw("COALESCE(reviewer.full_name, reviewer.username, '') AS reviewed_by_name"),
        ];
        foreach (['document_preview_path', 'document_back_preview_path'] as $previewColumn) {
            if (Schema::hasColumn('donor_verifications', $previewColumn)) {
                $select[] = 'dv.'.$previewColumn;
            }
        }

        $verifications = $query
            ->select($select)
            ->orderByRaw("CASE WHEN dv.status = 'pending' THEN 0 WHEN dv.status = 'rejected' THEN 1 ELSE 2 END")
            ->orderByDesc('dv.created_at')
            ->orderByDesc('dv.verification_id')
            ->paginate(12, ['*'], 'page', $focusId > 0 ? 1 : null)
            ->withQueryString();

        foreach ($verifications->getCollection() as $verification) {
            $verification->front_document_available = $this->storedDocumentExists((string) ($verification->document_path ?? ''));
            $verification->back_document_available = $this->storedDocumentExists((string) ($verification->document_back_path ?? ''));
        }

        $donorIds = $verifications->getCollection()
            ->pluck('donor_id')
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $histories = $this->verificationHistories($donorIds);
        $stats = $this->verificationStats();

        return view('admin.donor_verifications', [
            'verifications' => $verifications,
            'histories' => $histories,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'focusedVerificationId' => $focusId,
            'documentTypes' => $this->documentTypes(),
        ]);
    }

    public function document(Request $request, DonorVerification $verification): BinaryFileResponse
    {
        $side = $request->query('side', 'front');
        $rendition = $request->query('rendition', 'original');
        if (! is_string($side) || ! in_array($side, ['front', 'back'], true)
            || ! is_string($rendition) || ! in_array($rendition, ['preview', 'original'], true)) {
            abort(404, 'Verification document not found.');
        }

        $originalPath = trim((string) ($side === 'back'
            ? $verification->document_back_path
            : $verification->document_path));
        $previewPath = trim((string) ($side === 'back'
            ? $verification->document_back_preview_path
            : $verification->document_preview_path));
        $pathsToTry = $rendition === 'preview' && $previewPath !== ''
            ? [$previewPath, $originalPath]
            : [$originalPath];

        foreach ($pathsToTry as $docPath) {
            $response = $this->responseForStoredDocument($verification, $docPath, $side);
            if ($response instanceof BinaryFileResponse) {
                return $response;
            }
        }

        abort(404, 'Verification document not found.');
    }

    public function approve(Request $request, DonorVerification $verification): RedirectResponse
    {
        if (! $verification->donor_id || ! Donor::query()->where('donor_id', $verification->donor_id)->exists()) {
            return redirect()
                ->route('admin.donor-verifications.index')
                ->with('error', 'This submission is unlinked. Reconcile the donor account before approving it.');
        }

        if ($verification->status !== 'pending') {
            return redirect()
                ->route('admin.donor-verifications.index')
                ->with('error', 'Only pending verification requests can be approved.');
        }

        $donor = Donor::query()->find($verification->donor_id);
        $donorName = trim((string) ($donor?->first_name ?? '').' '.(string) ($donor?->last_name ?? ''));
        $donorName = $donorName !== '' ? $donorName : 'Donor #'.(int) $verification->donor_id;

        DB::transaction(function () use ($request, $verification): void {
            DonorVerification::query()
                ->where('verification_id', $verification->verification_id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'verified',
                    'rejection_reason' => null,
                    'reviewed_by_admin_id' => $this->adminId($request),
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            Donor::query()
                ->where('donor_id', $verification->donor_id)
                ->update(['verification_status' => 'verified']);
        });

        $this->createDonorNotification(
            (int) $verification->donor_id,
            'donor_verification_approved',
            'Your identity verification has been approved. You can now book a donation appointment.'
        );

        app(AdminNotificationService::class)->createAdminEvent(
            'donor_verification_approved',
            'Donor Verification Approved',
            "{$donorName}'s identity verification was approved.",
            'donor_verification',
            (int) $verification->verification_id
        );

        $this->writeAudit($request, 'donor_verification_approved', 'Approved donor identity verification.', (int) $verification->verification_id, [
            'donor_id' => (int) $verification->donor_id,
            'status' => 'verified',
        ]);

        return redirect()
            ->route('admin.donor-verifications.index')
            ->with('success', 'Donor verification approved.');
    }

    public function reject(Request $request, DonorVerification $verification): RedirectResponse
    {
        if (! $verification->donor_id || ! Donor::query()->where('donor_id', $verification->donor_id)->exists()) {
            return redirect()
                ->route('admin.donor-verifications.index')
                ->with('error', 'This submission is unlinked. Reconcile the donor account before rejecting it.');
        }

        if ($verification->status !== 'pending') {
            return redirect()
                ->route('admin.donor-verifications.index')
                ->with('error', 'Only pending verification requests can be rejected.');
        }

        $donor = Donor::query()->find($verification->donor_id);
        $donorName = trim((string) ($donor?->first_name ?? '').' '.(string) ($donor?->last_name ?? ''));
        $donorName = $donorName !== '' ? $donorName : 'Donor #'.(int) $verification->donor_id;

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $reason = trim((string) $validated['rejection_reason']);

        DB::transaction(function () use ($request, $verification, $reason): void {
            DonorVerification::query()
                ->where('verification_id', $verification->verification_id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'reviewed_by_admin_id' => $this->adminId($request),
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            Donor::query()
                ->where('donor_id', $verification->donor_id)
                ->update(['verification_status' => 'rejected']);
        });

        $this->createDonorNotification(
            (int) $verification->donor_id,
            'donor_verification_rejected',
            'Your identity verification was rejected. Reason: '.$reason
        );

        app(AdminNotificationService::class)->createAdminEvent(
            'donor_verification_rejected',
            'Donor Verification Rejected',
            "{$donorName}'s identity verification was rejected.",
            'donor_verification',
            (int) $verification->verification_id
        );

        $this->writeAudit($request, 'donor_verification_rejected', 'Rejected donor identity verification.', (int) $verification->verification_id, [
            'donor_id' => (int) $verification->donor_id,
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return redirect()
            ->route('admin.donor-verifications.index')
            ->with('success', 'Donor verification rejected.');
    }

    private function joinLatestDonorAuth($query)
    {
        if (! Schema::hasTable('donor_authentication')) {
            return $query->leftJoin(DB::raw('(select null as donor_id, null as email) as da'), function ($join): void {
                $join->on('da.donor_id', '=', 'd.donor_id');
            });
        }

        $latestAuth = DB::table('donor_authentication')
            ->select('donor_id', DB::raw('MAX(auth_id) as latest_auth_id'))
            ->groupBy('donor_id');

        return $query
            ->leftJoinSub($latestAuth, 'da_latest', function ($join): void {
                $join->on('da_latest.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('donor_authentication as da', 'da.auth_id', '=', 'da_latest.latest_auth_id');
    }

    private function responseForStoredDocument(DonorVerification $verification, string $docPath, string $side): ?BinaryFileResponse
    {
        $docPath = trim($docPath);
        $extension = strtolower((string) pathinfo($docPath, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if ($docPath === '' || str_contains($docPath, '..') || ! in_array($extension, $allowedExtensions, true)
            || ! (str_starts_with($docPath, 'donor-verifications/') || str_starts_with($docPath, 'uploads/verification/'))) {
            return null;
        }

        $candidates = [];
        foreach (['local', 'public'] as $diskName) {
            $disk = Storage::disk($diskName);
            if (! $disk->exists($docPath)) {
                continue;
            }

            $diskRoot = realpath($disk->path(''));
            $absolutePath = realpath($disk->path($docPath));
            if ($diskRoot && $absolutePath && is_file($absolutePath)
                && str_starts_with($absolutePath, $diskRoot.DIRECTORY_SEPARATOR)) {
                $candidates[] = $absolutePath;
            }
        }

        // Legacy mobile uploads used public/ folders. Keep admin access working
        // until those files are migrated, while serving them only through this route.
        foreach ([
            public_path($docPath),
            public_path('api/'.ltrim($docPath, '/')),
            public_path('api/'.str_replace('uploads/', 'upload/', ltrim($docPath, '/'))),
        ] as $publicPath) {
            $publicRoot = realpath(public_path());
            $absolutePath = realpath($publicPath);
            if ($publicRoot && $absolutePath && is_file($absolutePath)
                && str_starts_with($absolutePath, $publicRoot.DIRECTORY_SEPARATOR)) {
                $candidates[] = $absolutePath;
            }
        }

        foreach ($candidates as $absolutePath) {
            $size = filesize($absolutePath);
            if ($size === false || $size > 5 * 1024 * 1024) {
                abort(413, 'Verification document exceeds the permitted size.');
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath);
            if (! is_string($mime) || ! in_array($mime, $allowedMimes, true)) {
                abort(415, 'Verification document type is not supported.');
            }

            $suffix = $side === 'back' ? '-back' : '-front';
            $filename = 'donor-verification-'.(int) $verification->verification_id.$suffix.'.'.$extension;

            return response()->file($absolutePath, [
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                // Identity documents are sensitive; the stored preview is reused
                // server-side, but browser/shared caches must not retain it.
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            ]);
        }

        return null;
    }

    private function storedDocumentExists(string $docPath): bool
    {
        $docPath = trim($docPath);
        if ($docPath === '' || str_contains($docPath, '..')
            || ! (str_starts_with($docPath, 'donor-verifications/') || str_starts_with($docPath, 'uploads/verification/'))) {
            return false;
        }

        foreach (['local', 'public'] as $diskName) {
            $disk = Storage::disk($diskName);
            if (! $disk->exists($docPath)) {
                continue;
            }

            $root = realpath($disk->path(''));
            $absolute = realpath($disk->path($docPath));
            if ($root && $absolute && is_file($absolute)
                && str_starts_with($absolute, $root.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        $publicRoot = realpath(public_path());
        foreach ([
            public_path($docPath),
            public_path('api/'.ltrim($docPath, '/')),
            public_path('api/'.str_replace('uploads/', 'upload/', ltrim($docPath, '/'))),
        ] as $candidate) {
            $absolute = realpath($candidate);
            if ($publicRoot && $absolute && is_file($absolute)
                && str_starts_with($absolute, $publicRoot.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, int>  $donorIds
     * @return array<int, Collection<int, object>>
     */
    private function verificationHistories(array $donorIds): array
    {
        if ($donorIds === []) {
            return [];
        }

        return DB::table('donor_verifications as dv')
            ->leftJoin('admins as reviewer', 'reviewer.admin_id', '=', 'dv.reviewed_by_admin_id')
            ->whereIn('dv.donor_id', $donorIds)
            ->select([
                'dv.verification_id',
                'dv.donor_id',
                'dv.document_type',
                'dv.status',
                'dv.rejection_reason',
                'dv.reviewed_at',
                'dv.created_at',
                DB::raw("COALESCE(reviewer.full_name, reviewer.username, '') AS reviewed_by_name"),
            ])
            ->orderByDesc('dv.verification_id')
            ->get()
            ->groupBy('donor_id')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function verificationStats(): array
    {
        $counts = DB::table('donor_verifications')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'pending' => (int) ($counts['pending'] ?? 0),
            'verified' => (int) ($counts['verified'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function documentTypes(): array
    {
        return [
            'national_id' => 'National ID',
            'school_id' => 'School ID',
            'company_id' => 'Company ID',
            'barangay_certificate' => 'Barangay Certificate',
            'government_id' => 'Government ID',
            'other' => 'Other',
        ];
    }

    private function adminId(Request $request): ?int
    {
        return is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null;
    }

    private function createDonorNotification(int $donorId, string $type, string $message): void
    {
        if ($donorId <= 0 || ! Schema::hasTable('notifications')) {
            return;
        }

        try {
            $payload = [
                'donor_id' => $donorId,
                'message' => $message,
                'notification_type' => $type,
                'is_read' => 0,
                'created_at' => now(),
            ];

            if (Schema::hasColumn('notifications', 'push_sent')) {
                $payload['push_sent'] = 0;
            }

            DB::table('notifications')->insert($payload);
        } catch (Throwable $exception) {
            logger()->warning('Failed to create donor verification notification.', [
                'donor_id' => $donorId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function writeAudit(Request $request, string $actionType, string $description, ?int $verificationId, array $metadata = []): void
    {
        try {
            $actorName = trim((string) (
                $request->session()->get('admin_full_name')
                ?: $request->session()->get('admin_username')
                ?: 'Admin'
            ));
            $actorRole = Str::headline((string) $request->session()->get('admin_role', 'admin'));

            if (! Schema::hasTable('audit_logs')) {
                logger()->info($description, $metadata);

                return;
            }

            DB::table('audit_logs')->insert([
                'actor_admin_id' => $this->adminId($request),
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'action_type' => $actionType,
                'module_type' => 'donor_verification',
                'target_table' => 'donor_verifications',
                'target_id' => $verificationId,
                'description' => $description,
                'ip_address' => $request->ip(),
                'result' => 'success',
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Failed to write donor verification audit.', [
                'action_type' => $actionType,
                'verification_id' => $verificationId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
