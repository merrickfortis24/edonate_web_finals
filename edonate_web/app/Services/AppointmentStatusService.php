<?php

namespace App\Services;

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
            'confirmed', 'approved', 'scheduled' => self::CONFIRMED,
            'checked_in', 'checked in' => self::CHECKED_IN,
            'completed', 'complete', 'done' => self::COMPLETED,
            'deferred_on_site', 'deferred on site', 'onsite_deferred' => self::DEFERRED_ON_SITE,
            'no_show', 'no show', 'noshow' => self::NO_SHOW,
            'cancelled', 'canceled', 'rejected', 'declined' => self::CANCELLED,
            'rescheduled', 'reschedule requested' => self::RESCHEDULED,
            default => self::PENDING,
        };
    }

    public function canTransition(string $from, string $to): bool
    {
        $from = $this->normalize($from);
        $to = $this->normalize($to);

        return in_array($to, $this->allowedTransitions($from), true);
    }

    /**
     * @return array<int, string>
     */
    public function allowedTransitions(string $from): array
    {
        return match ($this->normalize($from)) {
            self::CONFIRMED => [self::CHECKED_IN, self::NO_SHOW, self::CANCELLED],
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
