<?php

namespace App\Observers;

use App\Models\Location;
use Throwable;

class LocationObserver
{
    private function syncToFirebase(Location $location): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('locations/' . $location->location_id)->set([
                'location_id' => $location->location_id,
                'street_address' => $location->street_address,
                'barangay_name' => $location->barangay_name,
                'city' => $location->city,
                'province' => $location->province,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function created(Location $location): void
    {
        $this->syncToFirebase($location);
    }

    public function updated(Location $location): void
    {
        $this->syncToFirebase($location);
    }

    public function deleted(Location $location): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('locations/' . $location->location_id)->remove();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
