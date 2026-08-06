<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DonationEvent;
use App\Models\Donor;
use App\Models\EligibilityStatus;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class AppointmentBookingService
{
    private const SLOT_CONSUMING_STATUSES = ['confirmed', 'approved', 'scheduled', 'rescheduled', 'checked_in', 'completed', 'complete', 'done'];

    private const CANCELLATION_STATUSES = ['cancelled', 'canceled', 'rejected', 'declined'];

    private const NO_SHOW_STATUSES = ['no_show', 'no show', 'noshow'];

    public function bookingReadiness(Donor $donor, ?Carbon $eventDate = null): array
    {
        $messages = [];

        if (! $this->donorAccountActive($donor)) {
            $messages[] = 'Your donor login account must be verified before booking an appointment.';
        }

        if (! $this->donorIdentityVerified($donor)) {
            $messages[] = 'Please complete identity verification before booking a donation appointment.';
        }

        $latestEligibility = $this->latestEligibility($donor);
        if (! $this->eligibilityAllowsBooking($latestEligibility, $eventDate)) {
            $messages[] = $latestEligibility
                ? 'Your latest eligibility result must be eligible for the selected event date before booking.'
                : 'Please complete eligibility screening before booking an appointment.';
        }

        return [
            'allowed' => $messages === [],
            'messages' => $messages,
            'latest_eligibility' => $latestEligibility,
        ];
    }

    public function book(Donor $donor, int $eventId, ?Request $request = null, ?string $appointmentTime = null): Appointment
    {
        return DB::transaction(function () use ($donor, $eventId, $request, $appointmentTime): Appointment {
            $donor = Donor::query()
                ->where('donor_id', $donor->donor_id)
                ->lockForUpdate()
                ->firstOrFail();

            $event = DonationEvent::query()
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if (! $event) {
                $this->fail('event_id', 'The selected donation event could not be found.');
            }

            $readiness = $this->bookingReadiness($donor, Carbon::parse($event->event_date));
            if (! $readiness['allowed']) {
                $this->fail('event_id', $readiness['messages'][0] ?? 'You are not allowed to book an appointment yet.');
            }

            if (! $this->eventAcceptsBookings($event)) {
                $this->fail('event_id', 'This donation event is not open for booking.');
            }

            if ($this->remainingSlots($event) <= 0) {
                $this->fail('event_id', 'This donation event is already fully booked.');
            }

            if ($this->hasDuplicateEventBooking((int) $donor->donor_id, (int) $event->event_id)) {
                $this->fail('event_id', 'You already have a booking for this donation event.');
            }

            $confirmedTime = $this->appointmentTimeForEvent($event, $appointmentTime);

            $appointment = Appointment::query()->create([
                'donor_id' => $donor->donor_id,
                'event_id' => $event->event_id,
                'appointment_date' => Carbon::parse($event->event_date)->toDateString(),
                'appointment_time' => $confirmedTime,
                'status' => 'confirmed',
                'created_at' => now(),
                'updated_at' => now(),
                'admin_id' => null,
                'donation_center' => $event->location_name,
            ]);

            $appointmentCode = $this->appointmentCode((int) $appointment->appointment_id);
            $eventTitle = (string) $event->title;

            $this->createDonorNotification(
                (int) $donor->donor_id,
                'appointment_booked',
                "Your appointment {$appointmentCode} for {$eventTitle} on " . Carbon::parse($event->event_date)->format('M j, Y') . " at " . Carbon::parse($confirmedTime)->format('g:i A') . ' has been confirmed.'
            );

            app(AdminNotificationService::class)->createAdminEvent(
                'appointment_booked',
                'Appointment Booked',
                $this->donorName($donor) . " booked confirmed appointment {$appointmentCode} for {$eventTitle}.",
                'appointment',
                (int) $appointment->appointment_id
            );

            $this->logAudit($request, $donor, 'appointment_created', "Created appointment {$appointmentCode}.", (int) $appointment->appointment_id, [
                'appointment_id' => (int) $appointment->appointment_id,
                'donor_id' => (int) $donor->donor_id,
                'event_id' => (int) $event->event_id,
                'status' => 'confirmed',
            ]);

            return $appointment->fresh(['event']) ?? $appointment;
        });
    }

    public function cancel(Donor $donor, int $appointmentId, ?string $reason = null, ?Request $request = null): Appointment
    {
        return DB::transaction(function () use ($donor, $appointmentId, $reason, $request): Appointment {
            $appointment = Appointment::query()
                ->where('appointment_id', $appointmentId)
                ->where('donor_id', $donor->donor_id)
                ->lockForUpdate()
                ->first();

            if (! $appointment) {
                $this->fail('appointment_id', 'Appointment not found.');
            }

            $normalizedStatus = $this->normalizeAppointmentStatus((string) $appointment->status);

            if (in_array($normalizedStatus, ['cancelled', 'completed', 'no_show'], true)) {
                $this->fail('appointment_id', 'This appointment can no longer be cancelled.');
            }

            if ($appointment->appointment_date && Carbon::parse($appointment->appointment_date)->lt(Carbon::today())) {
                $this->fail('appointment_id', 'Past appointments can no longer be cancelled.');
            }

            $appointment->fill([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'updated_at' => now(),
            ]);
            $appointment->save();

            $appointmentCode = $this->appointmentCode((int) $appointment->appointment_id);

            $this->createDonorNotification(
                (int) $donor->donor_id,
                'appointment_cancelled',
                "Your appointment {$appointmentCode} has been cancelled."
            );

            app(AdminNotificationService::class)->createAdminEvent(
                'appointment_cancelled',
                'Appointment Cancelled',
                $this->donorName($donor) . " cancelled appointment {$appointmentCode}.",
                'appointment',
                (int) $appointment->appointment_id
            );

            $this->logAudit($request, $donor, 'appointment_cancelled', "Cancelled appointment {$appointmentCode}.", (int) $appointment->appointment_id, [
                'appointment_id' => (int) $appointment->appointment_id,
                'donor_id' => (int) $donor->donor_id,
                'previous_status' => $normalizedStatus,
                'new_status' => 'cancelled',
                'reason' => $reason,
            ]);

            return $appointment->fresh(['event']) ?? $appointment;
        });
    }

    public function remainingSlots(DonationEvent $event): int
    {
        $capacity = max(0, (int) ($event->max_capacity ?? 0));

        return max(0, $capacity - $this->bookedSlotCount((int) $event->event_id));
    }

    public function bookedSlotCount(int $eventId): int
    {
        if ($eventId <= 0 || ! Schema::hasTable('appointments')) {
            return 0;
        }

        return (int) Appointment::query()
            ->where('event_id', $eventId)
            ->whereRaw("LOWER(COALESCE(status, '')) IN (" . $this->placeholders(self::SLOT_CONSUMING_STATUSES) . ')', self::SLOT_CONSUMING_STATUSES)
            ->count();
    }

    public function normalizeAppointmentStatus(string $status): string
    {
        $status = strtolower(trim($status));

        if (in_array($status, ['completed', 'complete', 'done'], true)) {
            return 'completed';
        }

        if (in_array($status, ['checked_in', 'checked in'], true)) {
            return 'checked_in';
        }

        if (in_array($status, self::CANCELLATION_STATUSES, true)) {
            return 'cancelled';
        }

        if (in_array($status, self::NO_SHOW_STATUSES, true)) {
            return 'no_show';
        }

        if (in_array($status, ['confirmed', 'approved', 'scheduled'], true)) {
            return 'confirmed';
        }

        if (in_array($status, ['rescheduled', 'reschedule requested'], true)) {
            return 'rescheduled';
        }

        return 'pending';
    }

    public function normalizeEventStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'open', 'upcoming', 'ongoing' => 'open',
            'closed' => 'closed',
            'cancelled', 'canceled' => 'cancelled',
            'completed', 'complete', 'done' => 'completed',
            default => 'closed',
        };
    }

    public function eventPayload(DonationEvent $event): array
    {
        $booked = $this->bookedSlotCount((int) $event->event_id);
        $capacity = max(0, (int) ($event->max_capacity ?? 0));

        return [
            'event_id' => (int) $event->event_id,
            'title' => (string) $event->title,
            'event_name' => (string) $event->title,
            'location_name' => (string) $event->location_name,
            'venue' => (string) $event->location_name,
            'address' => (string) ($event->address ?? ''),
            'event_date' => $event->event_date ? Carbon::parse($event->event_date)->toDateString() : null,
            'start_time' => $event->start_time ? (string) $event->start_time : null,
            'end_time' => $event->end_time ? (string) $event->end_time : null,
            'max_capacity' => $capacity,
            'maximum_slots' => $capacity,
            'confirmed_count' => $booked,
            'booked_slots' => $booked,
            'remaining_slots' => max(0, $capacity - $booked),
            'status' => $this->normalizeEventStatus((string) $event->status),
            'availability_status' => $this->availabilityStatus($event),
            'accepts_bookings' => $this->eventAcceptsBookings($event),
        ];
    }

    public function appointmentPayload(Appointment $appointment): array
    {
        $appointment->loadMissing('event');

        return [
            'appointment_id' => (int) $appointment->appointment_id,
            'appointment_code' => $this->appointmentCode((int) $appointment->appointment_id),
            'donor_id' => (int) $appointment->donor_id,
            'event_id' => $appointment->event_id !== null ? (int) $appointment->event_id : null,
            'event_name' => $appointment->event?->title,
            'venue' => $appointment->event?->location_name ?? $appointment->donation_center,
            'appointment_date' => $appointment->appointment_date ? Carbon::parse($appointment->appointment_date)->toDateString() : null,
            'appointment_time' => $appointment->appointment_time ? (string) $appointment->appointment_time : null,
            'status' => $this->normalizeAppointmentStatus((string) $appointment->status),
            'cancellation_reason' => $appointment->cancellation_reason,
            'created_at' => $appointment->created_at ? Carbon::parse($appointment->created_at)->toIso8601String() : null,
            'updated_at' => $appointment->updated_at ? Carbon::parse($appointment->updated_at)->toIso8601String() : null,
        ];
    }

    private function donorAccountActive(Donor $donor): bool
    {
        if (! Schema::hasTable('donor_authentication')
            || ! Schema::hasColumn('donor_authentication', 'is_verified')
            || ! Schema::hasColumn('donor_authentication', 'donor_id')) {
            return true;
        }

        $row = DB::table('donor_authentication')
            ->where('donor_id', $donor->donor_id)
            ->orderByDesc('auth_id')
            ->first(['is_verified']);

        return ! $row || (int) ($row->is_verified ?? 0) === 1;
    }

    private function donorIdentityVerified(Donor $donor): bool
    {
        if (! Schema::hasColumn('donors', 'verification_status')) {
            return false;
        }

        return strtolower(trim((string) $donor->verification_status)) === 'verified';
    }

    private function latestEligibility(Donor $donor): ?EligibilityStatus
    {
        if (! Schema::hasTable('eligibility_status')) {
            return null;
        }

        return EligibilityStatus::query()
            ->where('donor_id', $donor->donor_id)
            ->orderByDesc('eligibility_id')
            ->first();
    }

    private function eligibilityAllowsBooking(?EligibilityStatus $eligibility, ?Carbon $eventDate = null): bool
    {
        if (! $eligibility) {
            return false;
        }

        $status = strtolower(trim((string) $eligibility->status));
        if (! in_array($status, ['eligible', 'approved', 'qualified', 'ready'], true)) {
            return false;
        }

        if (! empty($eligibility->next_eligible_date)) {
            return Carbon::parse($eligibility->next_eligible_date)->lte($eventDate ?? Carbon::today());
        }

        return true;
    }

    private function eventAcceptsBookings(DonationEvent $event): bool
    {
        $status = $this->normalizeEventStatus((string) $event->status);
        if ($status !== 'open') {
            return false;
        }

        if (! $event->event_date || Carbon::parse($event->event_date)->lt(Carbon::today())) {
            return false;
        }

        return $this->remainingSlots($event) > 0;
    }

    private function hasDuplicateEventBooking(int $donorId, int $eventId): bool
    {
        return Appointment::query()
            ->where('donor_id', $donorId)
            ->where('event_id', $eventId)
            ->whereRaw("LOWER(COALESCE(status, '')) IN (" . $this->placeholders(self::SLOT_CONSUMING_STATUSES) . ')', self::SLOT_CONSUMING_STATUSES)
            ->exists();
    }

    private function appointmentTimeForEvent(DonationEvent $event, ?string $appointmentTime): string
    {
        $start = $event->start_time ? Carbon::parse($event->start_time) : null;
        $end = $event->end_time ? Carbon::parse($event->end_time) : null;
        $chosen = trim((string) $appointmentTime);

        if ($chosen === '' && $start instanceof Carbon) {
            return $start->format('H:i:s');
        }

        if ($chosen === '') {
            $this->fail('appointment_time', 'Please choose an appointment time.');
        }

        try {
            $time = Carbon::createFromFormat(strlen($chosen) === 5 ? 'H:i' : 'H:i:s', $chosen);
        } catch (Throwable) {
            $this->fail('appointment_time', 'Please choose a valid appointment time.');
        }

        if ($start instanceof Carbon && $time->lt($start)) {
            $this->fail('appointment_time', 'The appointment time must be within the selected event schedule.');
        }

        if ($end instanceof Carbon && $time->gt($end)) {
            $this->fail('appointment_time', 'The appointment time must be within the selected event schedule.');
        }

        return $time->format('H:i:s');
    }

    private function availabilityStatus(DonationEvent $event): string
    {
        $status = $this->normalizeEventStatus((string) $event->status);

        if ($status !== 'open') {
            return $status;
        }

        if (! $event->event_date || Carbon::parse($event->event_date)->lt(Carbon::today())) {
            return 'past';
        }

        return $this->remainingSlots($event) > 0 ? 'available' : 'full';
    }

    private function createDonorNotification(int $donorId, string $type, string $message): void
    {
        if ($donorId <= 0 || ! Schema::hasTable('notifications')) {
            return;
        }

        try {
            Notification::query()->create([
                'donor_id' => $donorId,
                'message' => $message,
                'notification_type' => $type,
                'is_read' => 0,
                'created_at' => now(),
                'push_sent' => 0,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function logAudit(?Request $request, Donor $donor, string $actionType, string $description, int $appointmentId, array $metadata): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        try {
            DB::table('audit_logs')->insert([
                'actor_admin_id' => null,
                'actor_name' => $this->donorName($donor),
                'actor_role' => 'Donor',
                'action_type' => $actionType,
                'module_type' => 'appointments',
                'target_table' => 'appointments',
                'target_id' => $appointmentId,
                'description' => $description,
                'ip_address' => $request?->ip(),
                'result' => 'success',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function donorName(Donor $donor): string
    {
        $name = trim((string) $donor->first_name . ' ' . (string) $donor->last_name);

        return $name !== '' ? $name : 'Donor #' . (int) $donor->donor_id;
    }

    private function appointmentCode(int $appointmentId): string
    {
        return 'AP' . str_pad((string) $appointmentId, 3, '0', STR_PAD_LEFT);
    }

    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
