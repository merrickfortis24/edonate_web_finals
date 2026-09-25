<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use App\Models\DonorVerification;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        $status = strtolower(trim((string) ($validated['status'] ?? '')));
        $search = trim((string) ($validated['search'] ?? ''));

        $query = DB::table('donor_verifications as dv')
            ->join('donors as d', 'd.donor_id', '=', 'dv.donor_id')
            ->leftJoin('admins as reviewer', 'reviewer.admin_id', '=', 'dv.reviewed_by_admin_id');

        $query = $this->joinLatestDonorAuth($query);

        if ($status !== '') {
            $query->where('dv.status', $status);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($builder) use ($like): void {
                $builder->whereRaw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) LIKE ?", [$like])
                    ->orWhere('d.contact_number', 'like', $like)
                    ->orWhere('da.email', 'like', $like);
            });
        }

        $verifications = $query
            ->select([
                'dv.verification_id',
                'dv.donor_id',
                'dv.document_type',
                'dv.document_path',
                'dv.status',
                'dv.rejection_reason',
                'dv.reviewed_by_admin_id',
                'dv.reviewed_at',
                'dv.created_at',
                'dv.updated_at',
                DB::raw("CONCAT(COALESCE(d.first_name,''),' ',COALESCE(d.last_name,'')) AS donor_name"),
                'd.contact_number',
                'd.verification_status',
                DB::raw("COALESCE(da.email, '') AS donor_email"),
                DB::raw("COALESCE(reviewer.full_name, reviewer.username, '') AS reviewed_by_name"),
            ])
            ->orderByRaw("CASE WHEN dv.status = 'pending' THEN 0 WHEN dv.status = 'rejected' THEN 1 ELSE 2 END")
            ->orderByDesc('dv.created_at')
            ->orderByDesc('dv.verification_id')
            ->paginate(12)
            ->withQueryString();

        $donorIds = $verifications->getCollection()
            ->pluck('donor_id')
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
            'documentTypes' => $this->documentTypes(),
        ]);
    }

    public function document(DonorVerification $verification): BinaryFileResponse
    {
        $docPath = $verification->document_path;
        $extension = pathinfo($docPath, PATHINFO_EXTENSION) ?: 'bin';
        $filename = 'donor-verification-' . (int) $verification->verification_id . '.' . $extension;

        // 1. Try Laravel local disk (storage/app/) — used by the web donor portal
        if (Storage::disk('local')->exists($docPath)) {
            $path = Storage::disk('local')->path($docPath);
            $mime = Storage::disk('local')->mimeType($docPath) ?: 'application/octet-stream';

            return response()->file($path, [
                'Content-Type'           => $mime,
                'Content-Disposition'    => 'inline; filename="' . $filename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // 2. Try public disk (storage/app/public/) — symlinked to public/storage/
        if (Storage::disk('public')->exists($docPath)) {
            $path = Storage::disk('public')->path($docPath);
            $mime = Storage::disk('public')->mimeType($docPath) ?: 'application/octet-stream';

            return response()->file($path, [
                'Content-Type'           => $mime,
                'Content-Disposition'    => 'inline; filename="' . $filename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // 3. Try direct public folder path — used by mobile app (e.g. public/uploads/verification/...)
        $publicPath = public_path($docPath);
        if (file_exists($publicPath)) {
            $mime = mime_content_type($publicPath) ?: 'application/octet-stream';

            return response()->file($publicPath, [
                'Content-Type'           => $mime,
                'Content-Disposition'    => 'inline; filename="' . $filename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort(404, 'Verification document not found.');
    }

    public function approve(Request $request, DonorVerification $verification): RedirectResponse
    {
        if ($verification->status !== 'pending') {
            return redirect()
                ->route('admin.donor-verifications.index')
                ->with('error', 'Only pending verification requests can be approved.');
        }

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
        if ($verification->status !== 'pending') {
            return redirect()
                ->route('admin.donor-verifications.index')
                ->with('error', 'Only pending verification requests can be rejected.');
        }

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
            'Your identity verification was rejected. Reason: ' . $reason
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

    /**
     * @param array<int, int> $donorIds
     * @return array<int, \Illuminate\Support\Collection<int, object>>
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
