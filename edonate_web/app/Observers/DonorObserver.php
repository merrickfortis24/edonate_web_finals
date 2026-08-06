<?php
namespace App\Observers;

use App\Models\Donor;
use Throwable;

class DonorObserver
{
    public function syncToFirebase(?Donor $donor)
    {
        try {
            $db = app('firebase.database');
            if (! $db || ! $donor) return;
            $ref = $db->getReference('donors/' . $donor->donor_id);
            $ref->set([
                'donor_id' => $donor->donor_id,
                'first_name' => $donor->first_name,
                'last_name' => $donor->last_name,
                'gender' => $donor->gender,
                'birthdate' => (string)$donor->birthdate,
                'contact_number' => $donor->contact_number,
                'blood_type_id' => $donor->blood_type_id,
                'location_id' => $donor->location_id,
                'date_registered' => (string)$donor->date_registered,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function created(Donor $donor) { $this->syncToFirebase($donor); }
    public function updated(Donor $donor) { $this->syncToFirebase($donor); }
    public function deleted(Donor $donor)
    {
        try {
            $db = app('firebase.database');
            if (! $db) return;
            $db->getReference('donors/' . $donor->donor_id)->remove();
        } catch (Throwable $e) { report($e); }
    }
}