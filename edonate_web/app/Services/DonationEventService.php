<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DonationEvent;
use App\Models\Notification;
use Carbon\Carbon;
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

    public function __construct(
        private readonly AppointmentBookingService $bookingService,
        private readonly EventPostPublisher $eventPostPublisher
    )
    {
    }

    /**
     * Create the source event and its donor-feed post as one atomic operation.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): DonationEvent
    {
        return DB::transaction(function () use ($attributes): DonationEvent {
            $event = DonationEvent::query()->create($attributes);
            $this->eventPostPublisher->sync($event);

            return $event->fresh() ?? $event;
        });
    }

    /**
     * Update an event and its linked post without replacing either record.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(DonationEvent $event, array $attributes): DonationEvent
    {
        return DB::transaction(function () use ($event, $attributes): DonationEvent {
            $lockedEvent = DonationEvent::query()
                ->where('event_id', $event->event_id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $this->normalizeEventStatus((string) $lockedEvent->status);
            $targetStatus = $this->normalizeEventStatus((string) ($attributes['status'] ?? $lockedEvent->status));
            $targetDate = $attributes['event_date'] ?? $lockedEvent->event_date;
            $this->assertValidStatusTransition($previousStatus, $targetStatus, $targetDate);

            if (isset($attributes['max_capacity'])
                && (int) $attributes['max_capacity'] < $this->bookedSlotCount((int) $lockedEvent->event_id)) {
                throw new \DomainException('The capacity cannot be lower than the number of currently confirmed appointments.');
            }

            $lockedEvent->fill($attributes);
            $lockedEvent->save();

            if ($targetStatus === 'cancelled') {
                $this->cancelFutureActiveAppointments($lockedEvent, 'Donation event was cancelled.');
            }

            $this->eventPostPublisher->sync($lockedEvent);

            return $lockedEvent->fresh() ?? $lockedEvent;
        });
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

            $currentStatus = $this->normalizeEventStatus((string) $lockedEvent->status);
            $this->assertValidStatusTransition(
                $currentStatus,
                $status,
                $lockedEvent->event_date
            );

            if ($status === 'cancelled') {
                $lockedEvent->status = 'cancelled';
                $lockedEvent->save();
                $this->cancelFutureActiveAppointments($lockedEvent, $reason);
                $this->eventPostPublisher->sync($lockedEvent);

                return $lockedEvent->fresh() ?? $lockedEvent;
            }

            if ($status === 'open' && $currentStatus !== 'open' && ! $this->canReopen($lockedEvent)) {
                throw new \DomainException('Only future closed events can be reopened.');
            }

            $lockedEvent->status = $status;
            $lockedEvent->save();
            $this->eventPostPublisher->sync($lockedEvent);

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

    private function assertValidStatusTransition(string $current, string $target, mixed $eventDate): void
    {
        if ($current === $target) {
            return;
        }

        if ($target === 'open') {
            $isFuture = $eventDate && Carbon::parse($eventDate)->gte(Carbon::today());
            if ($current === 'closed' && $isFuture) {
                return;
            }

            throw new \DomainException('Only future closed events can be reopened.');
        }

        $allowed = match ($target) {
            'closed' => $current === 'open',
            'completed', 'cancelled' => in_array($current, ['open', 'closed'], true),
            default => false,
        };

        if (! $allowed) {
            throw new \DomainException("A {$current} event cannot be changed to {$target}.");
        }
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
