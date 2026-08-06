<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DonationEvent;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DonationEventService
{
    private const ACTIVE_APPOINTMENT_STATUSES = [
        'pending',
        'confirmed',
        'approved',
        'scheduled',
        'rescheduled',
        'checked_in',
        'checked in',
    ];

    public function __construct(private readonly AppointmentBookingService $bookingService)
    {
    }

    public function bookedSlotCount(int $eventId): int
    {
        return $this->bookingService->bookedSlotCount($eventId);
    }

    public function changeStatus(DonationEvent $event, string $status, ?string $reason = null): DonationEvent
    {
        $status = $this->normalizeEventStatus($status);

        return DB::transaction(function () use ($event, $status, $reason): DonationEvent {
            $lockedEvent = DonationEvent::query()
                ->where('event_id', $event->event_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($status === 'cancelled') {
                $lockedEvent->status = 'cancelled';
                $lockedEvent->save();
                $this->cancelFutureActiveAppointments($lockedEvent, $reason);

                return $lockedEvent->fresh() ?? $lockedEvent;
            }

            if ($status === 'open' && ! $this->canReopen($lockedEvent)) {
                throw new \DomainException('Only future closed events can be reopened.');
            }

            $lockedEvent->status = $status;
            $lockedEvent->save();

            return $lockedEvent->fresh() ?? $lockedEvent;
        });
    }

    public function canReopen(DonationEvent $event): bool
    {
        $status = $this->normalizeEventStatus((string) $event->status);

        return $status === 'closed'
            && $event->event_date
            && now()->startOfDay()->lte($event->event_date);
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

    private function cancelFutureActiveAppointments(DonationEvent $event, ?string $reason): void
    {
        $appointments = Appointment::query()
            ->where('event_id', $event->event_id)
            ->whereDate('appointment_date', '>=', now()->toDateString())
            ->whereRaw(
                "LOWER(COALESCE(status, '')) IN (" . implode(',', array_fill(0, count(self::ACTIVE_APPOINTMENT_STATUSES), '?')) . ')',
                self::ACTIVE_APPOINTMENT_STATUSES
            )
            ->lockForUpdate()
            ->get();

        foreach ($appointments as $appointment) {
            $appointment->fill([
                'status' => 'cancelled',
                'cancellation_reason' => $reason ?: 'Donation event was cancelled.',
                'updated_at' => now(),
            ]);
            $appointment->save();

            $this->notifyDonor(
                (int) $appointment->donor_id,
                'appointment_cancelled',
                'Your appointment for ' . (string) $event->title . ' was cancelled because the donation event was cancelled.'
            );
        }
    }

    private function notifyDonor(int $donorId, string $type, string $message): void
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
}
