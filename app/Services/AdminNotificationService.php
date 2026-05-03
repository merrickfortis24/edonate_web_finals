<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminNotificationService
{
    /**
     * @var array<string, array<string, bool>>
     */
    private array $columnCache = [];

    public function create(array $data): ?Notification
    {
        if (! Schema::hasTable('notifications')) {
            return null;
        }

        $now = now();
        $type = $this->normalizeType((string) ($data['type'] ?? $data['notification_type'] ?? 'system'));
        $message = trim((string) ($data['message'] ?? ''));

        if ($message === '') {
            return null;
        }

        $values = [
            'donor_id' => $this->nullableInteger($data['donor_id'] ?? null),
            'title' => trim((string) ($data['title'] ?? $this->titleFromType($type))),
            'message' => $message,
            'notification_type' => $type,
            'channel' => $this->normalizeChannel((string) ($data['channel'] ?? 'system')),
            'recipient_type' => $this->normalizeRecipientType((string) ($data['recipient_type'] ?? 'admin')),
            'recipient_id' => $this->nullableInteger($data['recipient_id'] ?? null),
            'related_type' => $this->nullableString($data['related_type'] ?? null),
            'related_id' => $this->nullableInteger($data['related_id'] ?? null),
            'is_read' => 0,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insert = [];
        foreach ($values as $column => $value) {
            if ($this->hasColumn($column)) {
                $insert[$column] = $value;
            }
        }

        try {
            return Notification::query()->create($insert);
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
    ): ?Notification {
        return $this->create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'channel' => 'system',
            'recipient_type' => 'admin',
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
            'appointment_completed', 'donation_completed' => 'Donation Completed',
            'blood_stock_alert' => 'Low Blood Stock Alert',
            'report' => 'Report Generated',
            'eligibility_submitted' => 'Eligibility Review Submitted',
            'eligibility_reviewed' => 'Eligibility Review Updated',
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

    public function normalizeRecipientType(string $recipientType): string
    {
        $recipientType = Str::of($recipientType)->lower()->replace([' ', '-'], '_')->trim()->toString();

        return in_array($recipientType, ['admin', 'admins', 'system', 'all_donors', 'donor'], true)
            ? $recipientType
            : 'admin';
    }

    private function hasColumn(string $column): bool
    {
        if (! isset($this->columnCache['notifications'])) {
            $this->columnCache['notifications'] = [];
            foreach (Schema::getColumnListing('notifications') as $existingColumn) {
                $this->columnCache['notifications'][$existingColumn] = true;
            }
        }

        return isset($this->columnCache['notifications'][$column]);
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
