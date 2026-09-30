<?php

namespace App\Observers;

use App\Models\DonationRecord;
use App\Services\EligibilityWaitingPeriodService;
use Illuminate\Support\Facades\DB;
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
                'donation_status' => (string) $record->donation_status,
                'blood_units' => $record->blood_units,
                'verified_blood_type_id' => $record->verified_blood_type_id,
                'remarks' => $record->remarks,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function created(DonationRecord $record): void
    {
        $this->syncAfterCommit($record);
        $this->updateEligibilityStatus($record);
    }

    public function updated(DonationRecord $record): void
    {
        $this->syncAfterCommit($record);
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

    private function syncAfterCommit(DonationRecord $record): void
    {
        $donationId = (int) $record->donation_id;

        DB::afterCommit(function () use ($donationId): void {
            $committedRecord = DonationRecord::query()->find($donationId);
            if ($committedRecord) {
                $this->syncToFirebase($committedRecord);
            }
        });
    }
}
