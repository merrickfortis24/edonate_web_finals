<?php

namespace App\Observers;

use App\Models\EligibilityStatus;
use Throwable;

class EligibilityStatusObserver
{
    private function syncToFirebase(EligibilityStatus $status): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('eligibility/' . $status->eligibility_id)->set([
                'eligibility_id' => $status->eligibility_id,
                'donor_id' => $status->donor_id,
                'last_donation_date' => (string) $status->last_donation_date,
                'next_eligible_date' => (string) $status->next_eligible_date,
                'status' => $status->status,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function created(EligibilityStatus $status): void
    {
        $this->syncToFirebase($status);
    }

    public function updated(EligibilityStatus $status): void
    {
        $this->syncToFirebase($status);
    }

    public function deleted(EligibilityStatus $status): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('eligibility/' . $status->eligibility_id)->remove();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
