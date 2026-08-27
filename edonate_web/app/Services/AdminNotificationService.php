<?php

namespace App\Services;

use App\Mail\AdminNotificationMail;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminNotificationService
{
    /**
     * @var array<string, bool>|null
     */
    private ?array $columnCache = null;

    /**
     * Return the number of unread notifications shown in the admin bell.
     *
     * This deliberately reads admin_notifications rather than donor-facing
     * notifications so the badge matches the Admin Notification Center.
     */
    public function unreadCount(): int
    {
        if (! $this->hasAdminNotificationsTable() || ! $this->hasColumn('is_read')) {
            return 0;
        }

        return (int) AdminNotification::query()
            ->where(function ($query): void {
                $query->where('is_read', false)->orWhereNull('is_read');
            })
            ->count();
    }

    public function create(array $data): ?AdminNotification
    {
        if (! $this->hasAdminNotificationsTable()) {
            return null;
        }

        $type = $this->normalizeType((string) ($data['type'] ?? $data['notification_type'] ?? 'system'));
        $message = trim((string) ($data['message'] ?? ''));

        if ($message === '') {
            return null;
        }

        $values = [
            'title' => trim((string) ($data['title'] ?? $this->titleFromType($type))),
            'message' => $message,
            'notification_type' => $type,
            'channel' => $this->normalizeChannel((string) ($data['channel'] ?? 'system')),
            'related_type' => $this->nullableString($data['related_type'] ?? null),
            'related_id' => $this->nullableInteger($data['related_id'] ?? null),
            'is_read' => false,
            'read_at' => null,
        ];

        $insert = [];
        foreach ($values as $column => $value) {
            if ($this->hasColumn($column)) {
                $insert[$column] = $value;
            }
        }

        if ($insert === []) {
            return null;
        }

        try {
            $notification = AdminNotification::query()->create($insert);

            if ($notification !== null && $values['channel'] !== 'push') {
                $this->sendEmailNotifications($notification);
            }

            return $notification;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function createAdminEvent(
        string $type,
        string $title,
        string $message,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): ?AdminNotification {
        return $this->create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'channel' => 'system',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    }

    public function titleFromType(?string $type): string
    {
        $type = $this->normalizeType((string) $type);

        return match ($type) {
            'donor_registration' => 'New Donor Registration',
            'appointment_booked' => 'Appointment Booked',
            'appointment_cancelled', 'appointment_rejected' => 'Appointment Cancellation',
            'appointment_rescheduled' => 'Appointment Rescheduled',
            'appointment_approved' => 'Appointment Approved',
            'appointment_no_show' => 'Appointment No-Show',
            'appointment_checked_in' => 'Appointment Checked In',
            'appointment_deferred_on_site', 'donation_deferred' => 'Donation Deferred',
            'appointment_completed', 'donation_completed' => 'Donation Completed',
            'donation_event_open', 'donation_event_closed', 'donation_event_completed', 'donation_event_cancelled' => 'Donation Event Updated',
            'blood_stock_alert', 'facility_low_stock' => 'Low Blood Stock Alert',
            'facility_out_of_stock' => 'Blood Type Out of Stock',
            'facility_stock_recovered' => 'Blood Stock Recovered',
            'donor_verification_approved' => 'Donor Verification Approved',
            'donor_verification_rejected' => 'Donor Verification Rejected',
            'eligibility_auto_evaluated' => 'Eligibility Evaluated',
            'blood_request_created' => 'Blood Request Created',
            'blood_request_candidates_notified' => 'Blood Request Candidates Notified',
            'blood_request_cancelled' => 'Blood Request Cancelled',
            'blood_request_fulfilled', 'blood_request_manually_fulfilled' => 'Blood Request Fulfilled',
            'blood_request_donor_status_updated' => 'Blood Request Donor Updated',
            'report' => 'Report Generated',
            'eligibility_submitted' => 'Eligibility Review Submitted',
            'eligibility_reviewed' => 'Eligibility Review Updated',
            'donor_verification_submitted' => 'Donor Verification Submitted',
            default => Str::headline(str_replace('_', ' ', $type ?: 'system')),
        };
    }

    public function normalizeType(string $type): string
    {
        $type = Str::of($type)->lower()->replace([' ', '-'], '_')->squish()->toString();

        return $type !== '' ? $type : 'system';
    }

    public function normalizeChannel(string $channel): string
    {
        $channel = Str::of($channel)->lower()->trim()->toString();

        return in_array($channel, ['system', 'email', 'push'], true) ? $channel : 'system';
    }

    /**
     * Send an admin notification to admins who explicitly enabled email.
     *
     * Email delivery is intentionally isolated from the protected
     * admin_notifications table. A mail failure is logged and does not make
     * the in-app notification operation fail.
     */
    private function sendEmailNotifications(AdminNotification $notification): void
    {
        if (! $this->hasAdminNotificationPreferencesTable() || ! $this->hasAdminsTable()) {
            return;
        }

        try {
            $recipients = DB::table('admins as admins')
                ->join(
                    'admin_notification_preferences as preferences',
                    'preferences.admin_id',
                    '=',
                    'admins.admin_id'
                )
                ->where('preferences.email_enabled', true)
                ->whereNotNull('admins.email')
                ->where('admins.email', '<>', '')
                ->select([
                    'admins.admin_id',
                    'admins.email',
                    'admins.full_name',
                    'admins.username',
                ])
                ->distinct()
                ->get();
        } catch (Throwable $exception) {
            logger()->error('Unable to resolve admin email notification recipients.', [
                'admin_notification_id' => $notification->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            $email = trim((string) ($recipient->email ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            try {
                Mail::to($email)->send(new AdminNotificationMail(
                    $this->recipientName($recipient),
                    (string) ($notification->title ?? 'Admin Notification'),
                    (string) ($notification->message ?? ''),
                    (string) ($notification->notification_type ?? 'system'),
                    $notification->related_type !== null ? (string) $notification->related_type : null,
                    $notification->related_id !== null ? (int) $notification->related_id : null,
                ));
            } catch (Throwable $exception) {
                logger()->error('Failed to send admin notification email.', [
                    'admin_id' => (int) ($recipient->admin_id ?? 0),
                    'admin_notification_id' => $notification->getKey(),
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function recipientName(object $recipient): string
    {
        $fullName = trim((string) ($recipient->full_name ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string) ($recipient->username ?? ''));

        return $username !== '' ? $username : 'Admin';
    }

    private function hasAdminNotificationPreferencesTable(): bool
    {
        try {
            return Schema::hasTable('admin_notification_preferences')
                && Schema::hasColumn('admin_notification_preferences', 'admin_id')
                && Schema::hasColumn('admin_notification_preferences', 'email_enabled');
        } catch (Throwable $exception) {
            logger()->warning('Admin notification preference storage is unavailable.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function hasAdminsTable(): bool
    {
        try {
            return Schema::hasTable('admins')
                && Schema::hasColumn('admins', 'admin_id')
                && Schema::hasColumn('admins', 'email');
        } catch (Throwable $exception) {
            logger()->warning('Admin account storage is unavailable for email notifications.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function hasColumn(string $column): bool
    {
        if ($this->columnCache === null) {
            $this->columnCache = [];

            if (! $this->hasAdminNotificationsTable()) {
                return false;
            }

            try {
                foreach (Schema::getColumnListing('admin_notifications') as $existingColumn) {
                    $this->columnCache[$existingColumn] = true;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return isset($this->columnCache[$column]);
    }

    private function hasAdminNotificationsTable(): bool
    {
        try {
            return Schema::hasTable('admin_notifications');
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
