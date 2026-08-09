<?php

namespace App\Services;

use App\Models\AdminNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminNotificationService
{
    /**
     * @var array<string, bool>|null
     */
    private ?array $columnCache = null;

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
            return AdminNotification::query()->create($insert);
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
