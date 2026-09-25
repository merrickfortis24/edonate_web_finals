<?php

namespace App\Support;

final class EligibilityStatus
{
    public const ELIGIBLE = 'eligible';
    public const NOT_ELIGIBLE = 'not_eligible';
    public const TEMPORARY_DEFERRED = 'temporary_deferred';
    public const FOR_REVIEW = 'for_review';
    public const UNKNOWN = 'unknown';

    /** @var array<string, array{label: string, badge: string}> */
    private const PRESENTATION = [
        self::ELIGIBLE => ['label' => 'Eligible', 'badge' => 'bg-success'],
        self::NOT_ELIGIBLE => ['label' => 'Not Eligible', 'badge' => 'bg-danger'],
        self::TEMPORARY_DEFERRED => ['label' => 'Temporarily Deferred', 'badge' => 'bg-warning text-dark'],
        self::FOR_REVIEW => ['label' => 'For Review', 'badge' => 'bg-info text-dark'],
        self::UNKNOWN => ['label' => 'Unknown', 'badge' => 'bg-secondary'],
    ];

    public static function normalize(mixed $value): string
    {
        $status = strtolower(trim((string) ($value ?? '')));
        $status = preg_replace('/[\s-]+/', '_', $status) ?? $status;

        return match ($status) {
            'eligible', 'approved', 'qualified', 'ready' => self::ELIGIBLE,
            'not_eligible', 'ineligible', 'declined', 'rejected' => self::NOT_ELIGIBLE,
            'temporary_deferred', 'temporary_defer', 'temporarily_deferred', 'deferred' => self::TEMPORARY_DEFERRED,
            'for_review', 'pending_review', 'pending' => self::FOR_REVIEW,
            default => self::UNKNOWN,
        };
    }

    public static function label(mixed $value): string
    {
        return self::PRESENTATION[self::normalize($value)]['label'];
    }

    public static function badgeClass(mixed $value): string
    {
        return self::PRESENTATION[self::normalize($value)]['badge'];
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (string $value, array $presentation): array => [
                'value' => $value,
                'label' => $presentation['label'],
            ],
            array_keys(self::PRESENTATION),
            array_values(self::PRESENTATION)
        );
    }
}
