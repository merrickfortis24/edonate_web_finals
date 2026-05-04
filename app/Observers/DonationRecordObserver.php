<?php

namespace App\Observers;

use App\Models\DonationRecord;
use App\Models\EligibilityStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DonationRecordObserver
{
    private function syncToFirebase(DonationRecord $record): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('donation_records/' . $record->donation_id)->set([
                'donation_id' => $record->donation_id,
                'donor_id' => $record->donor_id,
                'appointment_id' => $record->appointment_id,
                'donation_date' => (string) $record->donation_date,
                'blood_units' => $record->blood_units,
                'remarks' => $record->remarks,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function created(DonationRecord $record): void
    {
        $this->syncToFirebase($record);
        $this->updateEligibilityStatus($record);
    }

    public function updated(DonationRecord $record): void
    {
        $this->syncToFirebase($record);
        $this->updateEligibilityStatus($record);
    }

    public function deleted(DonationRecord $record): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('donation_records/' . $record->donation_id)->remove();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function updateEligibilityStatus(DonationRecord $record): void
    {
        if (!$record->donation_date) {
            return;
        }

        $lastDonationDate = Carbon::parse($record->donation_date);
        $nextEligibleDate = $lastDonationDate->copy()->addMonths(3);
        $status = Carbon::now()->greaterThanOrEqualTo($nextEligibleDate) ? 'eligible' : 'not_eligible';
        $payload = [
            'last_donation_date' => $lastDonationDate->toDateString(),
            'next_eligible_date' => $nextEligibleDate->toDateString(),
            'status' => $status,
        ];

        if (Schema::hasColumn('eligibility_status', 'source')) {
            $payload['source'] = 'auto';
        }

        if (Schema::hasColumn('eligibility_status', 'result_reason')) {
            $payload['result_reason'] = $status === 'eligible'
                ? 'Donation interval requirement has been met.'
                : 'Recent donation requires waiting until the next eligible date.';
        }

        if (Schema::hasColumn('eligibility_status', 'recommendation_message')) {
            $payload['recommendation_message'] = $status === 'eligible'
                ? 'You may proceed with eligibility screening before your next donation.'
                : 'Please wait until your next eligible date before donating again.';
        }

        EligibilityStatus::updateOrCreate(
            ['donor_id' => $record->donor_id],
            $payload
        );
    }
}
