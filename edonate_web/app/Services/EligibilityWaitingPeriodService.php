<?php

namespace App\Services;

use App\Models\EligibilityStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class EligibilityWaitingPeriodService
{
    private const WAITING_PERIOD_DAYS = 56;

    public function markCompletedDonation(int $donorId, string $donationDate): EligibilityStatus
    {
        $nextEligibleDate = Carbon::parse($donationDate)->addDays(self::WAITING_PERIOD_DAYS)->toDateString();

        return $this->updateLatest($donorId, [
            'last_donation_date' => Carbon::parse($donationDate)->toDateString(),
            'next_eligible_date' => $nextEligibleDate,
            'status' => 'temporary_deferred',
            'result_reason' => 'Donor completed a blood donation and must complete the required waiting period.',
            'recommendation_message' => 'Donor may donate again on ' . Carbon::parse($nextEligibleDate)->format('F j, Y') . '.',
            'source' => 'auto',
            'reviewed_by_admin_id' => null,
            'reviewed_at' => null,
            'review_notes' => 'System-generated waiting period after completed donation.',
        ]);
    }

    public function markOnSiteDeferred(
        int $donorId,
        string $reason,
        ?string $nextEligibleDate,
        int $adminId,
        ?string $remarks = null
    ): EligibilityStatus {
        $normalizedDate = $nextEligibleDate !== null && trim($nextEligibleDate) !== ''
            ? Carbon::parse($nextEligibleDate)->toDateString()
            : null;

        $message = $normalizedDate
            ? 'Donor may be eligible again on ' . Carbon::parse($normalizedDate)->format('F j, Y') . '.'
            : 'Donor requires follow-up before donating again.';

        return $this->updateLatest($donorId, [
            'next_eligible_date' => $normalizedDate,
            'status' => 'temporary_deferred',
            'result_reason' => $reason,
            'recommendation_message' => $message,
            'source' => 'admin',
            'reviewed_by_admin_id' => $adminId,
            'reviewed_at' => now(),
            'review_notes' => $remarks ?: 'On-site deferral.',
        ]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function updateLatest(int $donorId, array $values): EligibilityStatus
    {
        $payload = ['donor_id' => $donorId] + $this->filterColumns($values);

        $record = EligibilityStatus::query()
            ->where('donor_id', $donorId)
            ->orderByDesc('eligibility_id')
            ->lockForUpdate()
            ->first();

        if ($record) {
            $record->fill($this->filterColumns($values));
            $record->save();

            return $record->refresh();
        }

        return EligibilityStatus::query()->create($payload);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function filterColumns(array $values): array
    {
        if (!Schema::hasTable('eligibility_status')) {
            return $values;
        }

        return collect($values)
            ->filter(fn($value, string $column): bool => Schema::hasColumn('eligibility_status', $column))
            ->all();
    }
}
