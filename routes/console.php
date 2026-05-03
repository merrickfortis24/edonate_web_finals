<?php

use App\Models\Appointment;
use App\Models\DonationRecord;
use App\Models\Donor;
use App\Models\DonorAuthentication;
use App\Models\EligibilityStatus;
use App\Models\Location;
use App\Models\Notification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('firebase:sync-mirror', function () {
    $database = app('firebase.database');

    if ($database === null) {
        $this->error('Firebase database is not configured. Check FIREBASE_DATABASE_URL.');
        return 1;
    }

    $this->info('Starting Firebase mirror sync...');

    foreach (Donor::query()->get() as $donor) {
        $database->getReference('donors/' . $donor->donor_id)->set([
            'donor_id' => $donor->donor_id,
            'first_name' => $donor->first_name,
            'last_name' => $donor->last_name,
            'gender' => $donor->gender,
            'birthdate' => (string) $donor->birthdate,
            'contact_number' => $donor->contact_number,
            'blood_type_id' => $donor->blood_type_id,
            'location_id' => $donor->location_id,
            'date_registered' => (string) $donor->date_registered,
        ]);
    }

    foreach (Location::query()->get() as $location) {
        $database->getReference('locations/' . $location->location_id)->set([
            'location_id' => $location->location_id,
            'street_address' => $location->street_address,
            'barangay_name' => $location->barangay_name,
            'city' => $location->city,
            'province' => $location->province,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ]);
    }

    foreach (Appointment::query()->get() as $appointment) {
        $database->getReference('appointments/' . $appointment->appointment_id)->set([
            'appointment_id' => $appointment->appointment_id,
            'donor_id' => $appointment->donor_id,
            'appointment_date' => (string) $appointment->appointment_date,
            'appointment_time' => (string) $appointment->appointment_time,
            'status' => $appointment->status,
            'created_at' => (string) $appointment->created_at,
            'admin_id' => $appointment->admin_id,
        ]);
    }

    foreach (Notification::query()->get() as $notification) {
        $database->getReference('notifications/' . $notification->notification_id)->set([
            'notification_id' => $notification->notification_id,
            'donor_id' => $notification->donor_id,
            'message' => $notification->message,
            'notification_type' => $notification->notification_type,
            'is_read' => (bool) $notification->is_read,
            'created_at' => (string) $notification->created_at,
            'push_sent' => (bool) $notification->push_sent,
        ]);
    }

    foreach (DonationRecord::query()->get() as $record) {
        $database->getReference('donation_records/' . $record->donation_id)->set([
            'donation_id' => $record->donation_id,
            'donor_id' => $record->donor_id,
            'appointment_id' => $record->appointment_id,
            'donation_date' => (string) $record->donation_date,
            'blood_units' => $record->blood_units,
            'remarks' => $record->remarks,
        ]);
    }

    foreach (EligibilityStatus::query()->get() as $eligibility) {
        $database->getReference('eligibility/' . $eligibility->eligibility_id)->set([
            'eligibility_id' => $eligibility->eligibility_id,
            'donor_id' => $eligibility->donor_id,
            'last_donation_date' => (string) $eligibility->last_donation_date,
            'next_eligible_date' => (string) $eligibility->next_eligible_date,
            'status' => $eligibility->status,
        ]);
    }

    foreach (DonorAuthentication::query()->get() as $authentication) {
        $database->getReference('donor_authentication/' . $authentication->donor_id)->set([
            'auth_id' => $authentication->auth_id,
            'donor_id' => $authentication->donor_id,
            'email' => $authentication->email,
            'is_verified' => (bool) $authentication->is_verified,
            'verified_at' => (string) $authentication->verified_at,
            'created_at' => (string) $authentication->created_at,
        ]);
    }

    $this->info('Firebase mirror sync completed.');
    return 0;
})->purpose('Sync mirrored SQL tables to Firebase Realtime Database');
