<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BloodType;
use App\Models\Donor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DonationProcessingService
{
    public function __construct(
        private readonly AppointmentStatusService $statuses,
        private readonly EligibilityWaitingPeriodService $eligibility
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function checkIn(int $appointmentId, int $adminId, ?Request $request = null): array
    {
        return DB::transaction(function () use ($appointmentId, $adminId, $request): array {
            $appointment = $this->lockedAppointment($appointmentId);
            $this->ensureTransition($appointment, AppointmentStatusService::CHECKED_IN);
            $this->ensureAppointmentCanBeProcessedToday($appointment);

            $appointment->forceFill([
                'status' => AppointmentStatusService::CHECKED_IN,
                'checked_in_at' => $appointment->checked_in_at ?: now(),
                'admin_id' => $adminId,
                'updated_at' => now(),
            ])->save();

            $this->audit($request, $adminId, 'appointment_checked_in', $appointment, 'Checked in appointment ' . $this->appointmentCode($appointment) . '.', [
                'previous_status' => $appointment->getOriginal('status'),
                'new_status' => AppointmentStatusService::CHECKED_IN,
            ]);

            return ['appointment' => $appointment->refresh(), 'already' => false];
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function completeDonation(int $appointmentId, int $adminId, array $data, ?Request $request = null): array
    {
        return DB::transaction(function () use ($appointmentId, $adminId, $data, $request): array {
            $appointment = $this->lockedAppointment($appointmentId);
            $existingRecord = $this->lockedDonationRecord($appointmentId);
            $normalized = $this->statuses->normalize((string) $appointment->status);

            if ($existingRecord && $normalized === AppointmentStatusService::COMPLETED) {
                return ['appointment' => $appointment, 'record' => $existingRecord, 'already' => true];
            }

            $this->ensureTransition($appointment, AppointmentStatusService::COMPLETED);

            if ($existingRecord) {
                throw ValidationException::withMessages([
                    'appointment' => 'This appointment already has a donation record.',
                ]);
            }

            $donor = Donor::query()
                ->where('donor_id', $appointment->donor_id)
                ->lockForUpdate()
                ->first();

            if (! $donor) {
                throw ValidationException::withMessages([
                    'donor' => 'The donor for this appointment could not be found.',
                ]);
            }

            $verifiedBloodType = $this->verifiedBloodType($data, $request);
            $bloodTypeChange = $this->prepareBloodTypeChange($donor, $verifiedBloodType, $data);

            $donationDate = Carbon::parse((string) ($data['donation_date'] ?? $appointment->appointment_date))->toDateString();
            $recordId = DB::table('donation_records')->insertGetId([
                'donor_id' => $appointment->donor_id,
                'appointment_id' => $appointment->appointment_id,
                'donation_date' => $donationDate,
                'donation_status' => 'completed',
                'blood_units' => (int) $data['blood_units'],
                'verified_blood_type_id' => $verifiedBloodType?->blood_type_id,
                'remarks' => $data['remarks'] ?? null,
                'deferred_reason' => null,
                'recorded_by_admin_id' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'donation_id');

            $appointment->forceFill([
                'status' => AppointmentStatusService::COMPLETED,
                'completed_at' => now(),
                'admin_id' => $adminId,
                'updated_at' => now(),
            ])->save();

            if ($verifiedBloodType) {
                $verifiedPayload = [
                    'blood_type_id' => $verifiedBloodType->blood_type_id,
                ];

                if (Schema::hasColumn('donors', 'blood_type_status')) {
                    $verifiedPayload['blood_type_status'] = 'verified';
                }
                if (Schema::hasColumn('donors', 'blood_type_verified_by_admin_id')) {
                    $verifiedPayload['blood_type_verified_by_admin_id'] = $adminId;
                }
                if (Schema::hasColumn('donors', 'blood_type_verified_at')) {
                    $verifiedPayload['blood_type_verified_at'] = now();
                }

                $donor->forceFill($verifiedPayload)->save();

                $this->auditBloodTypeVerification(
                    $request,
                    $adminId,
                    $donor,
                    $appointment,
                    $recordId,
                    $verifiedBloodType,
                    $bloodTypeChange
                );

                if ($bloodTypeChange['initial_verification']) {
                    $this->donorNotification(
                        (int) $donor->donor_id,
                        'blood_type_verified',
                        'Your blood type has been verified as ' . $verifiedBloodType->blood_type . ' during your completed donation.'
                    );
                }
            }

            $this->eligibility->markCompletedDonation((int) $appointment->donor_id, $donationDate);

            $this->donorNotification(
                (int) $appointment->donor_id,
                'donation_completed',
                'Thank you for donating blood. Your next eligibility date has been updated.'
            );

            $this->adminNotification(
                'donation_completed',
                'Donation Completed',
                $this->appointmentCode($appointment) . ' was completed and a donation record was created.',
                $appointment
            );

            $this->audit($request, $adminId, 'appointment_completed', $appointment, 'Completed appointment ' . $this->appointmentCode($appointment) . '.', [
                'donation_id' => $recordId,
                'blood_units' => (int) $data['blood_units'],
                'donation_status' => 'completed',
                'verified_blood_type_id' => $verifiedBloodType?->blood_type_id,
            ]);

            return [
                'appointment' => $appointment->refresh(),
                'record' => DB::table('donation_records')->where('donation_id', $recordId)->first(),
                'already' => false,
            ];
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function deferOnSite(int $appointmentId, int $adminId, array $data, ?Request $request = null): array
    {
        return DB::transaction(function () use ($appointmentId, $adminId, $data, $request): array {
            $appointment = $this->lockedAppointment($appointmentId);
            $existingRecord = $this->lockedDonationRecord($appointmentId);
            $normalized = $this->statuses->normalize((string) $appointment->status);

            if ($existingRecord && $normalized === AppointmentStatusService::DEFERRED_ON_SITE) {
                return ['appointment' => $appointment, 'record' => $existingRecord, 'already' => true];
            }

            $this->ensureTransition($appointment, AppointmentStatusService::DEFERRED_ON_SITE);

            if ($existingRecord) {
                throw ValidationException::withMessages([
                    'appointment' => 'This appointment already has a donation record.',
                ]);
            }

            $recordId = DB::table('donation_records')->insertGetId([
                'donor_id' => $appointment->donor_id,
                'appointment_id' => $appointment->appointment_id,
                'donation_date' => Carbon::parse((string) $appointment->appointment_date)->toDateString(),
                'donation_status' => 'deferred',
                'blood_units' => 0,
                'verified_blood_type_id' => null,
                'remarks' => $data['remarks'] ?? null,
                'deferred_reason' => $data['deferred_reason'],
                'recorded_by_admin_id' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'donation_id');

            $appointment->forceFill([
                'status' => AppointmentStatusService::DEFERRED_ON_SITE,
                'admin_id' => $adminId,
                'updated_at' => now(),
            ])->save();

            $this->eligibility->markOnSiteDeferred(
                (int) $appointment->donor_id,
                (string) $data['deferred_reason'],
                $data['next_eligible_date'] ?? null,
                $adminId,
                $data['remarks'] ?? null
            );

            $this->donorNotification(
                (int) $appointment->donor_id,
                'donation_deferred',
                'Your donation was deferred during on-site screening. Please review your updated eligibility status.'
            );

            $this->adminNotification(
                'donation_deferred',
                'Donation Deferred',
                $this->appointmentCode($appointment) . ' was deferred during on-site screening.',
                $appointment
            );

            $this->audit($request, $adminId, 'appointment_deferred_on_site', $appointment, 'Deferred appointment ' . $this->appointmentCode($appointment) . ' on site.', [
                'donation_id' => $recordId,
                'donation_status' => 'deferred',
                'has_next_eligible_date' => !empty($data['next_eligible_date']),
            ]);

            return [
                'appointment' => $appointment->refresh(),
                'record' => DB::table('donation_records')->where('donation_id', $recordId)->first(),
                'already' => false,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function markNoShow(int $appointmentId, int $adminId, ?Request $request = null): array
    {
        return DB::transaction(function () use ($appointmentId, $adminId, $request): array {
            $appointment = $this->lockedAppointment($appointmentId);
            $normalized = $this->statuses->normalize((string) $appointment->status);

            if ($normalized === AppointmentStatusService::NO_SHOW) {
                return ['appointment' => $appointment, 'already' => true];
            }

            $this->ensureTransition($appointment, AppointmentStatusService::NO_SHOW);
            $this->ensureAppointmentHasPassed($appointment);

            $appointment->forceFill([
                'status' => AppointmentStatusService::NO_SHOW,
                'admin_id' => $adminId,
                'updated_at' => now(),
            ])->save();

            $this->donorNotification(
                (int) $appointment->donor_id,
                'appointment_no_show',
                'Your appointment was marked as no-show.'
            );

            $this->adminNotification(
                'appointment_no_show',
                'Appointment No-Show',
                $this->appointmentCode($appointment) . ' was marked as no-show.',
                $appointment
            );

            $this->audit($request, $adminId, 'appointment_no_show', $appointment, 'Marked appointment ' . $this->appointmentCode($appointment) . ' as no-show.', [
                'previous_status' => $appointment->getOriginal('status'),
                'new_status' => AppointmentStatusService::NO_SHOW,
            ]);

            return ['appointment' => $appointment->refresh(), 'already' => false];
        });
    }

    private function lockedAppointment(int $appointmentId): Appointment
    {
        $appointment = Appointment::query()
            ->where('appointment_id', $appointmentId)
            ->lockForUpdate()
            ->first();

        if (!$appointment) {
            throw ValidationException::withMessages([
                'appointment' => 'Appointment not found.',
            ]);
        }

        return $appointment;
    }

    private function lockedDonationRecord(int $appointmentId): ?object
    {
        return DB::table('donation_records')
            ->where('appointment_id', $appointmentId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Resolve a supported laboratory blood type from a trusted lookup record.
     *
     * @param array<string, mixed> $data
     */
    private function verifiedBloodType(array $data, ?Request $request): ?BloodType
    {
        $bloodTypeId = isset($data['verified_blood_type_id']) && $data['verified_blood_type_id'] !== ''
            ? (int) $data['verified_blood_type_id']
            : null;

        if (! $bloodTypeId) {
            return null;
        }

        if (strtolower(trim((string) $request?->session()->get('admin_role'))) !== 'admin') {
            throw ValidationException::withMessages([
                'verified_blood_type_id' => 'Only an administrator may verify a donor blood type.',
            ]);
        }

        $bloodType = BloodType::query()->where('blood_type_id', $bloodTypeId)->first();
        if (! $bloodType || ! in_array(strtoupper(trim((string) $bloodType->blood_type)), ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
            throw ValidationException::withMessages([
                'verified_blood_type_id' => 'Select a supported verified blood type.',
            ]);
        }

        return $bloodType;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{initial_verification: bool, changed: bool, previous_blood_type_id: int|null, previous_blood_type_name: string|null, change_reason: string|null}
     */
    private function prepareBloodTypeChange(Donor $donor, ?BloodType $newBloodType, array $data): array
    {
        $currentStatus = strtolower(trim((string) ($donor->blood_type_status ?? 'not_yet_determined')));
        $currentTypeId = $donor->blood_type_id ? (int) $donor->blood_type_id : null;
        $currentTypeName = $currentTypeId
            ? BloodType::query()->where('blood_type_id', $currentTypeId)->value('blood_type')
            : null;
        $changed = $newBloodType !== null && $currentStatus === 'verified' && $currentTypeId !== (int) $newBloodType->blood_type_id;
        $reason = trim((string) ($data['blood_type_change_reason'] ?? ''));

        if ($changed) {
            if (empty($data['confirm_blood_type_change'])) {
                throw ValidationException::withMessages([
                    'confirm_blood_type_change' => 'Confirm the blood type correction before saving it.',
                ]);
            }

            if (mb_strlen($reason) < 8) {
                throw ValidationException::withMessages([
                    'blood_type_change_reason' => 'Provide a meaningful reason for changing a verified blood type.',
                ]);
            }
        }

        return [
            'initial_verification' => $newBloodType !== null && $currentStatus !== 'verified',
            'changed' => $changed,
            'previous_blood_type_id' => $currentTypeId,
            'previous_blood_type_name' => $currentTypeName ? (string) $currentTypeName : null,
            'change_reason' => $changed ? $reason : null,
        ];
    }

    private function ensureTransition(Appointment $appointment, string $targetStatus): void
    {
        if (!$this->statuses->canTransition((string) $appointment->status, $targetStatus)) {
            throw ValidationException::withMessages([
                'status' => 'This appointment cannot be moved to ' . str_replace('_', ' ', $targetStatus) . ' from its current status.',
            ]);
        }
    }

    private function ensureAppointmentCanBeProcessedToday(Appointment $appointment): void
    {
        $appointmentDate = Carbon::parse((string) $appointment->appointment_date)->startOfDay();

        if ($appointmentDate->isFuture()) {
            throw ValidationException::withMessages([
                'appointment_date' => 'This appointment cannot be checked in before its scheduled date.',
            ]);
        }
    }

    private function ensureAppointmentHasPassed(Appointment $appointment): void
    {
        $date = Carbon::parse((string) $appointment->appointment_date);
        $time = $appointment->appointment_time ? (string) $appointment->appointment_time : '23:59:59';
        $scheduledAt = Carbon::parse($date->toDateString() . ' ' . $time);

        if ($scheduledAt->isFuture()) {
            throw ValidationException::withMessages([
                'appointment_date' => 'Only past appointments can be marked as no-show.',
            ]);
        }
    }

    private function appointmentCode(Appointment $appointment): string
    {
        return 'AP' . str_pad((string) $appointment->appointment_id, 3, '0', STR_PAD_LEFT);
    }

    private function donorNotification(int $donorId, string $type, string $message): void
    {
        if ($donorId <= 0 || !Schema::hasTable('notifications')) {
            return;
        }

        $payload = [
            'donor_id' => $donorId,
            'message' => $message,
            'is_read' => false,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('notifications', 'notification_type')) {
            $payload['notification_type'] = $type;
        }

        if (Schema::hasColumn('notifications', 'type')) {
            $payload['type'] = $type;
        }

        if (Schema::hasColumn('notifications', 'push_sent')) {
            $payload['push_sent'] = 0;
        }

        DB::table('notifications')->insert($payload);
    }

    private function adminNotification(string $type, string $title, string $message, Appointment $appointment): void
    {
        app(AdminNotificationService::class)->createAdminEvent(
            $type,
            $title,
            $message,
            'appointment',
            (int) $appointment->appointment_id
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function audit(?Request $request, int $adminId, string $action, Appointment $appointment, string $description, array $metadata = []): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'actor_admin_id' => $adminId,
            'actor_name' => $this->adminName($request),
            'actor_role' => $request?->session()->get('admin_role'),
            'action_type' => $action,
            'module_type' => 'donation_processing',
            'target_table' => 'appointments',
            'target_id' => (int) $appointment->appointment_id,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'result' => 'success',
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }

    /**
     * @param array{initial_verification: bool, changed: bool, previous_blood_type_id: int|null, previous_blood_type_name: string|null, change_reason: string|null} $change
     */
    private function auditBloodTypeVerification(?Request $request, int $adminId, Donor $donor, Appointment $appointment, int $recordId, BloodType $bloodType, array $change): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $action = $change['changed'] ? 'blood_type_changed' : 'blood_type_verified';
        DB::table('audit_logs')->insert([
            'actor_admin_id' => $adminId,
            'actor_name' => $this->adminName($request),
            'actor_role' => $request?->session()->get('admin_role'),
            'action_type' => $action,
            'module_type' => 'blood_type_verification',
            'target_table' => 'donors',
            'target_id' => (int) $donor->donor_id,
            'description' => $change['changed']
                ? 'Updated a donor\'s verified blood type during donation completion.'
                : 'Verified a donor\'s blood type during donation completion.',
            'ip_address' => $request?->ip(),
            'result' => 'success',
            'metadata' => json_encode([
                'donor_id' => (int) $donor->donor_id,
                'donation_id' => $recordId,
                'appointment_id' => (int) $appointment->appointment_id,
                'blood_type_id' => (int) $bloodType->blood_type_id,
                'blood_type_name' => (string) $bloodType->blood_type,
                'previous_status' => $change['initial_verification'] ? 'not_verified' : 'verified',
                'previous_blood_type_id' => $change['previous_blood_type_id'],
                'previous_blood_type_name' => $change['previous_blood_type_name'],
                'change_reason' => $change['change_reason'],
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }

    private function adminName(?Request $request): ?string
    {
        $first = trim((string) $request?->session()->get('admin_first_name', ''));
        $last = trim((string) $request?->session()->get('admin_last_name', ''));
        $name = trim($first . ' ' . $last);

        return $name !== '' ? $name : $request?->session()->get('admin_name');
    }
}
