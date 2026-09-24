<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppointmentStatusService
{
    public const CONFIRMED = 'confirmed';
    public const CHECKED_IN = 'checked_in';
    public const COMPLETED = 'completed';
    public const DEFERRED_ON_SITE = 'deferred_on_site';
    public const NO_SHOW = 'no_show';
    public const CANCELLED = 'cancelled';
    public const PENDING = 'pending';
    public const RESCHEDULED = 'rescheduled';

    public function normalize(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'pending' => self::PENDING,
            'confirmed', 'approved', 'scheduled' => self::CONFIRMED,
            'checked_in', 'checked in' => self::CHECKED_IN,
            'completed', 'complete', 'done' => self::COMPLETED,
            'deferred_on_site', 'deferred on site', 'onsite_deferred' => self::DEFERRED_ON_SITE,
            'no_show', 'no show', 'noshow' => self::NO_SHOW,
            'cancelled', 'canceled', 'rejected', 'declined' => self::CANCELLED,
            'rescheduled', 'reschedule requested' => self::RESCHEDULED,
            default => 'unknown',
        };
    }

    public function canTransition(string $from, string $to): bool
    {
        $from = $this->normalize($from);
        $to = $this->normalize($to);

        return in_array($to, $this->allowedTransitions($from), true);
    }

    /**
     * Apply a legal appointment status transition while holding a row lock.
     *
     * @param array<string, mixed> $attributes
     * @param array<int, string>|null $allowedFrom
     * @return array{found: bool, transitioned: bool, already: bool, from: string|null, to: string, appointment: object|null}
     */
    public function transition(int $appointmentId, string $target, ?int $adminId = null, array $attributes = [], ?array $allowedFrom = null): array
    {
        $to = $this->normalize($target);

        return DB::transaction(function () use ($appointmentId, $to, $adminId, $attributes, $allowedFrom): array {
            $appointment = DB::table('appointments')
                ->where('appointment_id', $appointmentId)
                ->lockForUpdate()
                ->first();

            if (! $appointment) {
                return ['found' => false, 'transitioned' => false, 'already' => false, 'from' => null, 'to' => $to, 'appointment' => null];
            }

            $from = $this->normalize((string) ($appointment->status ?? ''));
            if ($from === $to) {
                return ['found' => true, 'transitioned' => false, 'already' => true, 'from' => $from, 'to' => $to, 'appointment' => $appointment];
            }

            if (($allowedFrom !== null && ! in_array($from, $allowedFrom, true)) || ! $this->canTransition($from, $to)) {
                return ['found' => true, 'transitioned' => false, 'already' => false, 'from' => $from, 'to' => $to, 'appointment' => $appointment];
            }

            $updates = ['status' => $to];
            if (Schema::hasColumn('appointments', 'admin_id')) {
                $updates['admin_id'] = $adminId;
            }
            if (array_key_exists('cancellation_reason', $attributes)
                && Schema::hasColumn('appointments', 'cancellation_reason')) {
                $updates['cancellation_reason'] = $attributes['cancellation_reason'];
            }
            if (Schema::hasColumn('appointments', 'updated_at')) {
                $updates['updated_at'] = now();
            }

            DB::table('appointments')->where('appointment_id', $appointmentId)->update($updates);
            $appointment->status = $to;

            return ['found' => true, 'transitioned' => true, 'already' => false, 'from' => $from, 'to' => $to, 'appointment' => $appointment];
        });
    }

    public function canReschedule(string $status): bool
    {
        return in_array(strtolower(trim($status)), [
            self::PENDING, self::CONFIRMED, 'approved', 'scheduled', self::RESCHEDULED, 'reschedule requested',
        ], true);
    }

    /**
     * @return array<int, string>
     */
    public function allowedTransitions(string $from): array
    {
        return match ($this->normalize($from)) {
            self::PENDING => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED, self::RESCHEDULED => [self::CHECKED_IN, self::NO_SHOW, self::CANCELLED],
            self::CHECKED_IN => [self::COMPLETED, self::DEFERRED_ON_SITE],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public function activeCapacityStatuses(): array
    {
        return [
            self::CONFIRMED,
            'approved',
            'scheduled',
            self::RESCHEDULED,
            self::CHECKED_IN,
            self::COMPLETED,
            'complete',
            'done',
        ];
    }
}
