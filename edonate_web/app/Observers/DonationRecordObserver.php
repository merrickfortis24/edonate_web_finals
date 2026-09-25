<?php

namespace App\Observers;

use App\Models\DonationRecord;
use App\Services\EligibilityWaitingPeriodService;
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
        if (! $record->donation_date || (string) ($record->getAttribute('donation_status') ?? '') !== ''
            && strtolower((string) $record->getAttribute('donation_status')) !== 'completed') {
            return;
        }

        // Phase 7 owns the waiting-period rule. Keeping this observer on the
        // same service prevents legacy Eloquent writes from reintroducing the
        // old three-month calculation.
        app(EligibilityWaitingPeriodService::class)->markCompletedDonation(
            (int) $record->donor_id,
            (string) $record->donation_date
        );
    }
}
