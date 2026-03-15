<?php

namespace App\Observers;

use App\Models\DonorAuthentication;
use Throwable;

class DonorAuthenticationObserver
{
    private function syncToFirebase(DonorAuthentication $authentication): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            // Mirror safe fields only; never send password or reset/verification tokens.
            $database->getReference('donor_authentication/' . $authentication->donor_id)->set([
                'auth_id' => $authentication->auth_id,
                'donor_id' => $authentication->donor_id,
                'email' => $authentication->email,
                'is_verified' => (bool) $authentication->is_verified,
                'verified_at' => (string) $authentication->verified_at,
                'created_at' => (string) $authentication->created_at,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function created(DonorAuthentication $authentication): void
    {
        $this->syncToFirebase($authentication);
    }

    public function updated(DonorAuthentication $authentication): void
    {
        $this->syncToFirebase($authentication);
    }

    public function deleted(DonorAuthentication $authentication): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('donor_authentication/' . $authentication->donor_id)->remove();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
