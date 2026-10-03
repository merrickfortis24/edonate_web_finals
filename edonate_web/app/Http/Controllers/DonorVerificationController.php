<?php

namespace App\Http\Controllers;

use App\Models\BloodType;
use App\Models\DonationRecord;
use App\Models\Donor;
use App\Models\DonorVerification;
use App\Models\EligibilityStatus;
use App\Models\Location;
use App\Models\Notification;
use App\Services\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class DonorVerificationController extends Controller
{
    public function index(Request $request)
    {
        $context = $this->buildContext($request, 'verification');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $donorId = (int) $context['donor']->donor_id;
        $latestEligibility = EligibilityStatus::query()
            ->where('donor_id', $donorId)
            ->orderByDesc('eligibility_id')
            ->first();

        $latestVerification = DonorVerification::query()
            ->where('donor_id', $donorId)
            ->orderByDesc('verification_id')
            ->first();

        $verificationHistory = DonorVerification::query()
            ->where('donor_id', $donorId)
            ->orderByDesc('verification_id')
            ->limit(10)
            ->get();

        return view('portal.verification', $context + [
            'latestEligibility' => $latestEligibility,
            'latestVerification' => $latestVerification,
            'verificationHistory' => $verificationHistory,
            'documentTypes' => $this->documentTypes(),
            'canSubmitVerification' => $this->canSubmitVerification($context['donor'], $latestEligibility, $latestVerification),
            'hasPassedEligibility' => $this->hasPassedEligibility($latestEligibility),
            'verificationStatus' => $this->verificationStatus($context['donor']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->buildContext($request, 'verification');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $donor = $context['donor'];
        $latestEligibility = EligibilityStatus::query()
            ->where('donor_id', $donor->donor_id)
            ->orderByDesc('eligibility_id')
            ->first();

        $latestVerification = DonorVerification::query()
            ->where('donor_id', $donor->donor_id)
            ->orderByDesc('verification_id')
            ->first();

        if (! $this->canSubmitVerification($donor, $latestEligibility, $latestVerification)) {
            return redirect()
                ->route('donor.verification.index')
                ->with('error', $this->verificationBlockedMessage($donor, $latestEligibility, $latestVerification));
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(array_keys($this->documentTypes()))],
            'document' => [
                'required',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
            ],
        ], [
            'document.max' => 'The document must not be larger than 5MB.',
            'document.mimes' => 'Please upload a JPG, PNG, or PDF document.',
            'document.mimetypes' => 'Please upload a valid JPG, PNG, or PDF document.',
        ]);

        $file = $validated['document'];
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin'));
        $path = 'donor-verifications/' . (int) $donor->donor_id . '/' . (string) Str::uuid() . '.' . $extension;

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        $verification = null;
        try {
            DB::transaction(function () use ($donor, $validated, $path, &$verification): void {
                $verification = DonorVerification::query()->create([
                    'donor_id' => (int) $donor->donor_id,
                    'document_type' => $validated['document_type'],
                    'document_path' => $path,
                    'status' => 'pending',
                    'rejection_reason' => null,
                    'reviewed_by_admin_id' => null,
                    'reviewed_at' => null,
                ]);

                if (Schema::hasColumn('donors', 'verification_status')) {
                    Donor::query()
                        ->where('donor_id', $donor->donor_id)
                        ->update(['verification_status' => 'pending']);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            report($exception);

            return redirect()
                ->route('donor.verification.index')
                ->with('error', 'Unable to submit your verification document right now. Please try again.');
        }

        $verificationId = $verification instanceof DonorVerification ? (int) $verification->verification_id : null;
        $this->writeAudit($request, 'donor_verification_submitted', 'Donor submitted an identity verification document.', $verificationId, [
            'donor_id' => (int) $donor->donor_id,
            'document_type' => $validated['document_type'],
            'status' => 'pending',
        ]);

        $donorName = trim((string) $donor->first_name . ' ' . (string) $donor->last_name);
        app(AdminNotificationService::class)->createAdminEvent(
            'donor_verification_submitted',
            'Donor Verification Submitted',
            "{$donorName} submitted an identity verification document.",
            'donor_verification',
            $verificationId
        );

        return redirect()
            ->route('donor.verification.index')
            ->with('success', 'Your identity document was submitted for admin verification.');
    }

    private function buildContext(Request $request, string $activeNav): array|RedirectResponse
    {
        $donorId = (int) $request->session()->get('donor_id');

        if ($donorId <= 0) {
            return redirect('/login')->with('error', 'Please log in to continue.');
        }

        $donor = Donor::query()->find($donorId);
        if (! $donor) {
            $request->session()->forget(['donor_auth_id', 'donor_id', 'donor_email', 'donor_name']);
            return redirect('/login')->with('error', 'Your account could not be found. Please log in again.');
        }

        $location = $donor->location_id ? Location::query()->find($donor->location_id) : null;
        $bloodType = $donor->blood_type_id
            ? BloodType::query()->where('blood_type_id', $donor->blood_type_id)->value('blood_type')
            : null;

        $totalDonations = DonationRecord::query()
            ->where('donor_id', $donor->donor_id)
            ->count();

        $notificationQuery = Notification::query()
            ->where('donor_id', $donor->donor_id)
            ->when(Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'recipient_type'), function ($query): void {
                $query->where(function ($builder): void {
                    $builder->whereNull('recipient_type')
                    ->orWhereIn('recipient_type', ['donor', 'all_donors']);
                });
            });

        $alertsCount = (clone $notificationQuery)
            ->where('is_read', 0)
            ->count();

        $notificationBanner = (clone $notificationQuery)
            ->where('is_read', 0)
            ->orderByDesc('created_at')
            ->orderByDesc('notification_id')
            ->first();

        return [
            'donor' => $donor,
            'location' => $location,
            'user' => (object) [
                'first_name' => $donor->first_name,
                'last_name' => $donor->last_name,
                'blood_type' => $bloodType ?? '-',
                'total_donations' => $totalDonations,
            ],
            'navLinks' => $this->navLinks(),
            'activeNav' => $activeNav,
            'alertsCount' => $alertsCount,
            'notificationBanner' => $notificationBanner,
            'totalDonations' => $totalDonations,
        ];
    }

    private function navLinks(): array
    {
        return [
            ['key' => 'home', 'label' => 'Home', 'href' => route('donor.dashboard')],
            ['key' => 'book', 'label' => 'Book Appointment', 'href' => route('donor.book-appointment')],
            ['key' => 'eligibility', 'label' => 'Check Eligibility', 'href' => route('donor.check-eligibility')],
            ['key' => 'verification', 'label' => 'Verify Identity', 'href' => route('donor.verification.index')],
            ['key' => 'history', 'label' => 'History', 'href' => route('donor.history')],
            ['key' => 'alerts', 'label' => 'Alerts', 'href' => route('donor.alerts')],
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

    private function canSubmitVerification(Donor $donor, ?EligibilityStatus $eligibility, ?DonorVerification $verification): bool
    {
        $status = $this->verificationStatus($donor);

        return $this->hasPassedEligibility($eligibility)
            && in_array($status, ['unverified', 'rejected'], true)
            && ! ($verification instanceof DonorVerification && $verification->status === 'pending');
    }

    private function verificationBlockedMessage(Donor $donor, ?EligibilityStatus $eligibility, ?DonorVerification $verification): string
    {
        if (! $this->hasPassedEligibility($eligibility)) {
            return 'Please pass the eligibility screening before submitting identity verification.';
        }

        $status = $this->verificationStatus($donor);
        if ($status === 'pending' || ($verification instanceof DonorVerification && $verification->status === 'pending')) {
            return 'Your identity verification is already pending admin review.';
        }

        if ($status === 'verified') {
            return 'Your identity is already verified.';
        }

        return 'Identity verification cannot be submitted right now.';
    }

    private function hasPassedEligibility(?EligibilityStatus $eligibility): bool
    {
        $status = strtolower(trim((string) ($eligibility?->status ?? '')));

        return in_array($status, ['eligible', 'approved'], true);
    }

    private function verificationStatus(Donor $donor): string
    {
        $status = strtolower(trim((string) ($donor->verification_status ?? 'unverified')));

        return in_array($status, ['unverified', 'pending', 'verified', 'rejected'], true) ? $status : 'unverified';
    }

    private function writeAudit(Request $request, string $actionType, string $description, ?int $verificationId, array $metadata = []): void
    {
        try {
            if (! Schema::hasTable('audit_logs')) {
                logger()->info($description, $metadata);
                return;
            }

            $donorName = trim((string) ($request->session()->get('donor_name') ?: 'Donor'));

            DB::table('audit_logs')->insert([
                'actor_admin_id' => null,
                'actor_name' => $donorName !== '' ? $donorName : 'Donor',
                'actor_role' => 'Donor',
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
