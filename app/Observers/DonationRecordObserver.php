<?php

namespace App\Observers;

use App\Models\DonationRecord;
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
    }

    public function updated(DonationRecord $record): void
    {
        $this->syncToFirebase($record);
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
}
