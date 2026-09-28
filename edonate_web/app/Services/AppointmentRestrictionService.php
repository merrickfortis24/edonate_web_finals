<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentCancellation;
use App\Models\AppointmentRestriction;
use App\Models\AppointmentRestrictionAppeal;
use App\Models\Donor;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AppointmentRestrictionService
{
    public const CANCELLATION_LIMIT = 3;

    public const DEFAULT_RESTRICTION_REASON = 'Your account has been temporarily restricted because you cancelled three consecutive donation appointments.';

    public function isRestricted(Donor $donor): bool
    {
        return Schema::hasColumn('donors', 'appointment_restricted')
            && (bool) $donor->getAttribute('appointment_restricted');
    }

    /** Record and score a donor-originated cancellation while the donor row is locked. */
    public function recordDonorCancellation(Donor $donor, Appointment $appointment, string $reason, ?Request $request = null): int
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'Please provide a reason for cancelling this appointment.',
            ]);
        }

        $previousCount = max(0, (int) ($donor->consecutive_cancellations ?? 0));
        $count = $previousCount + 1;
        $now = now();

        AppointmentCancellation::query()->create([
            'donor_id' => (int) $donor->donor_id,
            'appointment_id' => (int) $appointment->appointment_id,
            'cancelled_at' => $now,
            'reason' => $reason,
            'cancelled_by' => 'donor',
            'consecutive_count' => $count,
        ]);

        $wasRestricted = $this->isRestricted($donor);
        DB::table('donors')->where('donor_id', $donor->donor_id)->update([
            'consecutive_cancellations' => $count,
        ]);
        $donor->setAttribute('consecutive_cancellations', $count);

        $this->audit(
            $request,
            $donor,
            'appointment_cancelled_by_donor',
            'Donor cancelled appointment AP'.str_pad((string) $appointment->appointment_id, 3, '0', STR_PAD_LEFT).'.',
            (int) $appointment->appointment_id,
            [
                'donor_id' => (int) $donor->donor_id,
                'appointment_id' => (int) $appointment->appointment_id,
                'previous_consecutive_count' => $previousCount,
                'consecutive_count' => $count,
                'cancelled_by' => 'donor',
                'reason' => $reason,
            ],
            'appointments',
            (int) $appointment->appointment_id
        );

        if ($count >= self::CANCELLATION_LIMIT && ! $wasRestricted) {
            $restrictionId = DB::table('appointment_restrictions')->insertGetId([
                'donor_id' => (int) $donor->donor_id,
                'status' => 'active',
                'restriction_reason' => self::DEFAULT_RESTRICTION_REASON,
                'restricted_at' => $now,
                'restricted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'restriction_id');

            DB::table('donors')->where('donor_id', $donor->donor_id)->update([
                'appointment_restricted' => true,
                'restriction_status' => 'restricted',
                'restriction_reason' => self::DEFAULT_RESTRICTION_REASON,
                'restricted_at' => $now,
                'restricted_by' => null,
            ]);
            $donor->setAttribute('appointment_restricted', true);
            $donor->setAttribute('restriction_status', 'restricted');
            $donor->setAttribute('restriction_reason', self::DEFAULT_RESTRICTION_REASON);
            $donor->setAttribute('restricted_at', $now);

            $this->notifyDonor(
                (int) $donor->donor_id,
                'appointment_restricted',
                'Your appointment privileges have been temporarily restricted after three consecutive appointment cancellations. Please submit a justification or contact the administrator to request account review.'
            );
            app(AdminNotificationService::class)->createAdminEvent(
                'appointment_restriction_review_required',
                'Appointment Restriction Review Required',
                $this->donorName($donor).' has reached three consecutive appointment cancellations and has been temporarily restricted.',
                'appointment_restriction',
                (int) $restrictionId
            );
            $this->audit(
                $request,
                $donor,
                'donor_appointment_restricted',
                $this->donorName($donor).' was temporarily restricted after three consecutive donor cancellations.',
                (int) $appointment->appointment_id,
                [
                    'donor_id' => (int) $donor->donor_id,
                    'appointment_id' => (int) $appointment->appointment_id,
                    'restriction_id' => (int) $restrictionId,
                    'previous_status' => 'clear',
                    'new_status' => 'restricted',
                    'consecutive_count' => $count,
                    'reason' => self::DEFAULT_RESTRICTION_REASON,
                ],
                'donors',
                (int) $donor->donor_id
            );

            return $count;
        }

        if ($count < self::CANCELLATION_LIMIT) {
            $warning = $count === 1
                ? 'You have cancelled 1 consecutive appointment. Please remember that 3 consecutive cancellations may temporarily restrict your appointment privileges.'
                : 'Warning: You have cancelled 2 consecutive appointments. One more consecutive cancellation will temporarily restrict your appointment privileges and require administrator review.';
            $this->notifyDonor((int) $donor->donor_id, 'appointment_cancellation_warning', $warning);
            $this->audit(
                $request,
                $donor,
                'appointment_cancellation_warning',
                $warning,
                (int) $appointment->appointment_id,
                [
                    'donor_id' => (int) $donor->donor_id,
                    'appointment_id' => (int) $appointment->appointment_id,
                    'consecutive_count' => $count,
                    'reason' => $reason,
                ],
                'donors',
                (int) $donor->donor_id
            );
        }

        return $count;
    }

    public function submitAppeal(Donor $donor, string $justification, ?Request $request = null): AppointmentRestrictionAppeal
    {
        return DB::transaction(function () use ($donor, $justification, $request): AppointmentRestrictionAppeal {
            $lockedDonor = Donor::query()->where('donor_id', $donor->donor_id)->lockForUpdate()->firstOrFail();
            if (! $this->isRestricted($lockedDonor)) {
                throw ValidationException::withMessages(['justification' => 'There is no active appointment restriction to appeal.']);
            }

            $restriction = AppointmentRestriction::query()
                ->where('donor_id', $lockedDonor->donor_id)
                ->where('status', 'active')
                ->orderByDesc('restriction_id')
                ->lockForUpdate()
                ->first();
            if (! $restriction) {
                throw ValidationException::withMessages(['justification' => 'The active restriction could not be found. Please contact the administrator.']);
            }

            $pendingExists = AppointmentRestrictionAppeal::query()
                ->where('restriction_id', $restriction->restriction_id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();
            if ($pendingExists) {
                throw ValidationException::withMessages(['justification' => 'You already have a pending review request.']);
            }

            $appeal = AppointmentRestrictionAppeal::query()->create([
                'donor_id' => (int) $lockedDonor->donor_id,
                'restriction_id' => (int) $restriction->restriction_id,
                'justification' => trim($justification),
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            $this->notifyDonor(
                (int) $lockedDonor->donor_id,
                'appointment_restriction_appeal_submitted',
                'Your appointment restriction review request has been submitted. The eDonate administrator will review your justification.'
            );
            app(AdminNotificationService::class)->createAdminEvent(
                'appointment_restriction_appeal_submitted',
                'Appointment Restriction Appeal Submitted',
                $this->donorName($lockedDonor).' submitted a justification for appointment restriction review.',
                'appointment_restriction_appeal',
                (int) $appeal->appeal_id
            );
            $this->audit(
                $request,
                $lockedDonor,
                'restriction_appeal_submitted',
                $this->donorName($lockedDonor).' submitted an appointment restriction appeal.',
                (int) $appeal->appeal_id,
                ['donor_id' => (int) $lockedDonor->donor_id, 'restriction_id' => (int) $restriction->restriction_id, 'appeal_id' => (int) $appeal->appeal_id],
                'appointment_restriction_appeals',
                (int) $appeal->appeal_id
            );

            return $appeal;
        });
    }

    public function reviewAppeal(int $appealId, int $adminId, string $decision, ?string $notes, ?Request $request = null): AppointmentRestrictionAppeal
    {
        $donorId = (int) AppointmentRestrictionAppeal::query()->where('appeal_id', $appealId)->value('donor_id');
        if ($donorId <= 0) {
            throw ValidationException::withMessages(['appeal' => 'The appeal could not be found.']);
        }

        return DB::transaction(function () use ($appealId, $donorId, $adminId, $decision, $notes, $request): AppointmentRestrictionAppeal {
            $donor = Donor::query()->where('donor_id', $donorId)->lockForUpdate()->firstOrFail();
            $appeal = AppointmentRestrictionAppeal::query()->where('appeal_id', $appealId)->lockForUpdate()->firstOrFail();
            $restriction = AppointmentRestriction::query()->where('restriction_id', $appeal->restriction_id)->lockForUpdate()->firstOrFail();

            if ($appeal->status !== 'pending' || $restriction->status !== 'active' || ! $this->isRestricted($donor)) {
                throw ValidationException::withMessages(['appeal' => 'This appeal is no longer pending review.']);
            }

            $adminNotes = trim((string) $notes);
            if ($decision === 'approve') {
                $this->liftLocked($donor, $restriction, $adminId, $adminNotes, $request, $appeal, 'appeal_approved');
            } else {
                $appeal->forceFill([
                    'status' => 'rejected',
                    'reviewed_at' => now(),
                    'reviewed_by' => $adminId,
                    'admin_notes' => $adminNotes,
                ])->save();
                $restriction->forceFill(['admin_notes' => $adminNotes])->save();
                $this->createReview($restriction, $appeal, $adminId, 'appeal_rejected', $adminNotes);
                $this->notifyDonor(
                    $donorId,
                    'appointment_restriction_appeal_rejected',
                    $adminNotes !== ''
                        ? 'Your appointment restriction appeal was not approved. Administrator note: '.$adminNotes
                        : 'Your appointment restriction appeal was not approved. Your appointment privileges remain restricted.'
                );
                $this->audit(
                    $request,
                    $donor,
                    'restriction_appeal_rejected',
                    $this->donorName($donor).' had an appointment restriction appeal rejected.',
                    $appealId,
                    ['donor_id' => $donorId, 'appeal_id' => $appealId, 'restriction_id' => (int) $restriction->restriction_id, 'previous_status' => 'pending', 'new_status' => 'rejected', 'admin_id' => $adminId, 'reason' => $adminNotes],
                    'appointment_restriction_appeals',
                    $appealId,
                    $adminId
                );
            }

            return $appeal->refresh();
        });
    }

    public function liftManually(Donor $donor, int $adminId, string $notes, ?Request $request = null): void
    {
        DB::transaction(function () use ($donor, $adminId, $notes, $request): void {
            $lockedDonor = Donor::query()->where('donor_id', $donor->donor_id)->lockForUpdate()->firstOrFail();
            if (! $this->isRestricted($lockedDonor)) {
                throw ValidationException::withMessages(['donor' => 'This donor does not have an active appointment restriction.']);
            }
            $restriction = AppointmentRestriction::query()
                ->where('donor_id', $lockedDonor->donor_id)
                ->where('status', 'active')
                ->orderByDesc('restriction_id')
                ->lockForUpdate()
                ->first();
            if (! $restriction) {
                throw ValidationException::withMessages(['donor' => 'The active restriction could not be found.']);
            }

            $pendingAppeal = AppointmentRestrictionAppeal::query()
                ->where('restriction_id', $restriction->restriction_id)
                ->where('status', 'pending')
                ->orderByDesc('appeal_id')
                ->lockForUpdate()
                ->first();

            $this->liftLocked($lockedDonor, $restriction, $adminId, trim($notes), $request, $pendingAppeal, 'manual_lift');
        });
    }

    public function keepRestricted(Donor $donor, int $adminId, string $notes, ?Request $request = null): void
    {
        DB::transaction(function () use ($donor, $adminId, $notes, $request): void {
            $lockedDonor = Donor::query()->where('donor_id', $donor->donor_id)->lockForUpdate()->firstOrFail();
            if (! $this->isRestricted($lockedDonor)) {
                throw ValidationException::withMessages(['donor' => 'This donor does not have an active appointment restriction.']);
            }
            $restriction = AppointmentRestriction::query()
                ->where('donor_id', $lockedDonor->donor_id)
                ->where('status', 'active')
                ->orderByDesc('restriction_id')
                ->lockForUpdate()
                ->firstOrFail();
            $adminNotes = trim($notes);
            $restriction->forceFill(['admin_notes' => $adminNotes])->save();
            $this->createReview($restriction, null, $adminId, 'kept_restricted', $adminNotes);
            $this->audit(
                $request,
                $lockedDonor,
                'appointment_restriction_kept',
                'Administrator kept '.$this->donorName($lockedDonor).' restricted from appointment booking.',
                (int) $restriction->restriction_id,
                ['donor_id' => (int) $lockedDonor->donor_id, 'restriction_id' => (int) $restriction->restriction_id, 'previous_status' => 'restricted', 'new_status' => 'restricted', 'admin_id' => $adminId, 'reason' => $adminNotes],
                'appointment_restrictions',
                (int) $restriction->restriction_id,
                $adminId
            );
        });
    }

    public function resetStreakAfterCompletion(Donor $donor, int $appointmentId, int $adminId, ?Request $request = null): void
    {
        $previousCount = max(0, (int) ($donor->consecutive_cancellations ?? 0));
        if ($previousCount === 0) {
            return;
        }

        DB::table('donors')->where('donor_id', $donor->donor_id)->update(['consecutive_cancellations' => 0]);
        $donor->setAttribute('consecutive_cancellations', 0);
        $this->audit(
            $request,
            $donor,
            'appointment_cancellation_streak_reset',
            'A completed donation reset the donor cancellation streak.',
            $appointmentId,
            ['donor_id' => (int) $donor->donor_id, 'appointment_id' => $appointmentId, 'previous_consecutive_count' => $previousCount, 'new_consecutive_count' => 0, 'admin_id' => $adminId],
            'donors',
            (int) $donor->donor_id,
            $adminId
        );
    }

    public function dashboardCounts(): array
    {
        return [
            'restricted_donors' => Schema::hasColumn('donors', 'appointment_restricted')
                ? (int) DB::table('donors')->where('appointment_restricted', true)->count()
                : 0,
            'pending_appeals' => Schema::hasTable('appointment_restriction_appeals')
                ? (int) DB::table('appointment_restriction_appeals')->where('status', 'pending')->count()
                : 0,
        ];
    }

    private function liftLocked(
        Donor $donor,
        AppointmentRestriction $restriction,
        int $adminId,
        string $notes,
        ?Request $request,
        ?AppointmentRestrictionAppeal $appeal,
        string $action
    ): void {
        $now = now();
        if ($appeal) {
            $appeal->forceFill([
                'status' => 'approved',
                'reviewed_at' => $now,
                'reviewed_by' => $adminId,
                'admin_notes' => $notes,
            ])->save();
        }

        $restriction->forceFill([
            'status' => 'lifted',
            'lifted_at' => $now,
            'lifted_by' => $adminId,
            'admin_notes' => $notes,
        ])->save();

        DB::table('donors')->where('donor_id', $donor->donor_id)->update([
            'appointment_restricted' => false,
            'consecutive_cancellations' => 0,
            'restriction_status' => 'lifted',
            'restriction_lifted_at' => $now,
            'restriction_lifted_by' => $adminId,
        ]);

        $this->createReview($restriction, $appeal, $adminId, $action, $notes);
        $this->notifyDonor(
            (int) $donor->donor_id,
            $action === 'appeal_approved' ? 'appointment_restriction_appeal_approved' : 'appointment_restriction_lifted',
            $action === 'appeal_approved'
                ? 'Your request has been reviewed and your appointment privileges have been restored. You may now book donation appointments again.'
                : 'An administrator has restored your appointment privileges. You may now book donation appointments again.'
        );

        $auditAction = $action === 'appeal_approved'
            ? 'restriction_appeal_approved'
            : 'appointment_restriction_lifted';
        $this->audit(
            $request,
            $donor,
            $auditAction,
            $this->donorName($donor).' had their appointment restriction lifted by an administrator.',
            $appeal ? (int) $appeal->appeal_id : (int) $restriction->restriction_id,
            [
                'donor_id' => (int) $donor->donor_id,
                'restriction_id' => (int) $restriction->restriction_id,
                'appeal_id' => $appeal ? (int) $appeal->appeal_id : null,
                'previous_status' => 'restricted',
                'new_status' => 'lifted',
                'previous_consecutive_count' => (int) ($donor->consecutive_cancellations ?? 0),
                'new_consecutive_count' => 0,
                'admin_id' => $adminId,
                'reason' => $notes,
            ],
            'appointment_restrictions',
            (int) $restriction->restriction_id,
            $adminId
        );
    }

    private function createReview(AppointmentRestriction $restriction, ?AppointmentRestrictionAppeal $appeal, int $adminId, string $action, string $notes): void
    {
        DB::table('appointment_restriction_reviews')->insert([
            'restriction_id' => (int) $restriction->restriction_id,
            'appeal_id' => $appeal ? (int) $appeal->appeal_id : null,
            'admin_id' => $adminId > 0 ? $adminId : null,
            'action' => $action,
            'notes' => $notes !== '' ? $notes : null,
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notifyDonor(int $donorId, string $type, string $message): void
    {
        if ($donorId <= 0 || ! Schema::hasTable('notifications')) {
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
            $payload['push_sent'] = false;
        }

        try {
            Notification::query()->create($payload);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function audit(
        ?Request $request,
        Donor $donor,
        string $action,
        string $description,
        int $targetId,
        array $metadata,
        string $targetTable,
        int $auditTargetId,
        ?int $adminId = null
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        try {
            $metadata['user_agent'] = $request?->userAgent();
            DB::table('audit_logs')->insert([
                'actor_admin_id' => $adminId,
                'actor_name' => $adminId ? $this->adminName($request) : $this->donorName($donor),
                'actor_role' => $adminId ? (string) $request?->session()->get('admin_role', 'admin') : 'Donor',
                'action_type' => $action,
                'module_type' => 'appointment_restrictions',
                'target_table' => $targetTable,
                'target_id' => $auditTargetId ?: $targetId,
                'description' => mb_substr($description, 0, 255),
                'ip_address' => $request?->ip(),
                'result' => 'success',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function donorName(Donor $donor): string
    {
        $name = trim((string) $donor->first_name.' '.(string) $donor->last_name);

        return $name !== '' ? $name : 'Donor #'.(int) $donor->donor_id;
    }

    private function adminName(?Request $request): ?string
    {
        return trim((string) ($request?->session()->get('admin_full_name') ?: $request?->session()->get('admin_username') ?: 'Administrator'));
    }
}
