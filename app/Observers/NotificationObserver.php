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
                'title' => $notification->title,
                'message' => $notification->message,
                'notification_type' => $notification->notification_type,
                'channel' => $notification->channel,
                'recipient_type' => $notification->recipient_type,
                'recipient_id' => $notification->recipient_id,
                'related_type' => $notification->related_type,
                'related_id' => $notification->related_id,
                'is_read' => (bool) ($notification->is_read || $notification->read_at),
                'read_at' => $notification->read_at ? (string) $notification->read_at : null,
                'created_at' => (string) $notification->created_at,
                'updated_at' => $notification->updated_at ? (string) $notification->updated_at : null,
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
