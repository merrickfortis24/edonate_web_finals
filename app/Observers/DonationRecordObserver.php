<?php

namespace App\Observers;

use App\Models\DonationRecord;
use App\Models\EligibilityStatus;
use Carbon\Carbon;
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

        EligibilityStatus::updateOrCreate(
            ['donor_id' => $record->donor_id],
            [
                'last_donation_date' => $lastDonationDate->toDateString(),
                'next_eligible_date' => $nextEligibleDate->toDateString(),
                'status' => $status
            ]
        );
    }
}
