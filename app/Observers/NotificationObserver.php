<?php

namespace App\Observers;

use App\Models\Notification;
use Throwable;

class NotificationObserver
{
    private function syncToFirebase(Notification $notification): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('notifications/' . $notification->notification_id)->set([
                'notification_id' => $notification->notification_id,
                'donor_id' => $notification->donor_id,
                'message' => $notification->message,
                'notification_type' => $notification->notification_type,
                'is_read' => (bool) $notification->is_read,
                'created_at' => (string) $notification->created_at,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function created(Notification $notification): void
    {
        $this->syncToFirebase($notification);
    }

    public function updated(Notification $notification): void
    {
        $this->syncToFirebase($notification);
    }

    public function deleted(Notification $notification): void
    {
        try {
            $database = app('firebase.database');
            if ($database === null) {
                return;
            }

            $database->getReference('notifications/' . $notification->notification_id)->remove();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
