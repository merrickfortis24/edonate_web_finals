<?php

namespace App\Services;

use App\Models\Appointment;
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

            $donationDate = Carbon::parse((string) ($data['donation_date'] ?? $appointment->appointment_date))->toDateString();
            $recordId = DB::table('donation_records')->insertGetId([
                'donor_id' => $appointment->donor_id,
                'appointment_id' => $appointment->appointment_id,
                'donation_date' => $donationDate,
                'donation_status' => 'completed',
                'blood_units' => (int) $data['blood_units'],
                'verified_blood_type_id' => $data['verified_blood_type_id'] ?? null,
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

    private function adminName(?Request $request): ?string
    {
        $first = trim((string) $request?->session()->get('admin_first_name', ''));
        $last = trim((string) $request?->session()->get('admin_last_name', ''));
        $name = trim($first . ' ' . $last);

        return $name !== '' ? $name : $request?->session()->get('admin_name');
    }
}
