<?php

namespace App\Observers;

use App\Models\Appointment;
use Throwable;

class AppointmentObserver
{
    private function syncToFirebase(Appointment $appointment): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('appointments/' . $appointment->appointment_id)->set([
                'appointment_id' => $appointment->appointment_id,
                'donor_id' => $appointment->donor_id,
                'appointment_date' => (string) $appointment->appointment_date,
                'appointment_time' => (string) $appointment->appointment_time,
                'status' => $appointment->status,
                'created_at' => (string) $appointment->created_at,
                'admin_id' => $appointment->admin_id,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function created(Appointment $appointment): void
    {
        $this->syncToFirebase($appointment);
    }

    public function updated(Appointment $appointment): void
    {
        $this->syncToFirebase($appointment);
    }

    public function deleted(Appointment $appointment): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('appointments/' . $appointment->appointment_id)->remove();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
