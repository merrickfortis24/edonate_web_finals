<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class NotificationController extends Controller
{
    private const FILTERS = [
        'all' => 'All Notifications',
        'unread' => 'Unread',
        'read' => 'Read',
        'donor_registration' => 'Donor Registration',
        'appointment' => 'Appointment',
        'donation' => 'Donation',
        'blood_stock_alert' => 'Blood Stock Alert',
        'low_stock' => 'Low Stock',
        'blood_request' => 'Blood Request',
        'donor_verification' => 'Donor Verification',
        'eligibility' => 'Eligibility',
        'event' => 'Donation Event',
        'report' => 'Report',
        'system' => 'System',
    ];

    private const TYPES = [
        'system' => 'System',
        'donor_registration' => 'Donor Registration',
        'appointment' => 'Appointment',
        'appointment_booked' => 'Appointment Booked',
        'appointment_cancelled' => 'Appointment Cancellation',
        'appointment_rescheduled' => 'Appointment Rescheduled',
        'appointment_approved' => 'Appointment Approved',
        'appointment_rejected' => 'Appointment Rejected',
        'appointment_no_show' => 'Appointment No-Show',
        'donation' => 'Donation',
        'donation_completed' => 'Donation Completed',
        'blood_stock_alert' => 'Blood Stock Alert',
        'facility_low_stock' => 'Low Blood Stock Alert',
        'facility_out_of_stock' => 'Blood Type Out of Stock',
        'facility_stock_recovered' => 'Blood Stock Recovered',
        'report' => 'Report',
        'eligibility_submitted' => 'Eligibility Submitted',
        'eligibility_reviewed' => 'Eligibility Reviewed',
        'eligibility_auto_evaluated' => 'Eligibility Evaluated',
        'donor_verification_submitted' => 'Donor Verification Submitted',
        'donor_verification_approved' => 'Donor Verification Approved',
        'donor_verification_rejected' => 'Donor Verification Rejected',
        'blood_request_created' => 'Blood Request Created',
        'blood_request_candidates_notified' => 'Blood Request Candidates Notified',
        'blood_request_cancelled' => 'Blood Request Cancelled',
        'blood_request_fulfilled' => 'Blood Request Fulfilled',
        'blood_request_manually_fulfilled' => 'Blood Request Fulfilled',
        'blood_request_donor_status_updated' => 'Blood Request Donor Updated',
        'next_eligible_reminder' => 'Next Eligible Reminder',
    ];

    public function __construct(private readonly AdminNotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        return view('admin.notification_center', [
            'notificationPayload' => [
                'api' => [
                    'listUrl' => route('admin.notifications.data'),
                    'storeUrl' => route('admin.notifications.store'),
                    'detailBaseUrl' => url('/admin/notifications'),
                    'markReadBaseUrl' => url('/admin/notifications'),
                    'markAllReadUrl' => route('admin.notifications.read-all'),
                    'deleteBaseUrl' => url('/admin/notifications'),
                    'clearAllUrl' => route('admin.notifications.clear-all'),
                ],
                'summary' => $this->summary(),
                'filters' => $this->filterOptions(),
                'types' => $this->typeOptions(),
                'channels' => [
                    ['value' => 'system', 'label' => 'System'],
                    ['value' => 'email', 'label' => 'Email'],
                ],
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'string', Rule::in(array_keys(self::FILTERS))],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);
        $filter = (string) ($validated['filter'] ?? 'all');

        if (! $this->hasAdminNotificationsTable()) {
            return response()->json($this->emptyPayload($page, $perPage));
        }

        try {
            $query = $this->filteredQuery($filter);

            if ($this->hasColumn('created_at')) {
                $query->orderByDesc('created_at');
            }

            $query->orderByDesc('admin_notification_id');

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json($this->emptyPayload($page, $perPage));
        }

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (AdminNotification $notification): array => $this->transform($notification))
                ->values()
                ->all(),
            'summary' => $this->summary(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function show(int $notification): JsonResponse
    {
        $notification = $this->findNotification($notification);
        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        return response()->json([
            'data' => $this->transform($notification, true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->hasAdminNotificationsTable()) {
            return response()->json([
                'message' => 'Admin notification storage is not ready. Run the admin_notifications migration first.',
                'summary' => $this->summary(),
            ], 409);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'channel' => ['required', 'string', Rule::in(['system', 'email'])],
        ]);

        $notification = $this->notificationService->create($validated);
        if (! $notification) {
            return response()->json(['message' => 'Unable to create notification.'], 500);
        }

        return response()->json([
            'message' => 'Notification created.',
            'data' => $this->transform($notification, true),
            'summary' => $this->summary(),
        ], 201);
    }

    public function markRead(int $notification): JsonResponse
    {
        $notification = $this->findNotification($notification);
        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $updates = [];
        if ($this->hasColumn('is_read')) {
            $updates['is_read'] = true;
        }
        if ($this->hasColumn('read_at')) {
            $updates['read_at'] = $notification->read_at ?: now();
        }
        if ($updates !== []) {
            $notification->forceFill($updates)->save();
        }

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $this->transform($notification->refresh(), true),
            'summary' => $this->summary(),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $count = 0;

        if (! $this->hasAdminNotificationsTable()) {
            return response()->json([
                'message' => 'No unread notifications to update.',
                'summary' => $this->summary(),
            ]);
        }

        try {
            $this->unreadQuery()
                ->chunkById(100, function ($notifications) use (&$count): void {
                    foreach ($notifications as $notification) {
                        $updates = [];
                        if ($this->hasColumn('is_read')) {
                            $updates['is_read'] = true;
                        }
                        if ($this->hasColumn('read_at')) {
                            $updates['read_at'] = $notification->read_at ?: now();
                        }
                        if ($updates !== []) {
                            $notification->forceFill($updates)->save();
                        }
                        $count++;
                    }
                }, 'admin_notification_id', 'admin_notification_id');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to mark notifications as read.',
                'summary' => $this->summary(),
            ], 500);
        }

        return response()->json([
            'message' => $count > 0 ? "{$count} notifications marked as read." : 'No unread notifications to update.',
            'summary' => $this->summary(),
        ]);
    }

    public function destroy(int $notification): JsonResponse
    {
        $notification = $this->findNotification($notification);
        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $this->deleteNotification($notification);

        return response()->json([
            'message' => 'Notification deleted.',
            'summary' => $this->summary(),
        ]);
    }

    public function clearAll(): JsonResponse
    {
        $count = 0;

        if (! $this->hasAdminNotificationsTable()) {
            return response()->json([
                'message' => 'No notifications to clear.',
                'summary' => $this->summary(),
            ]);
        }

        try {
            AdminNotification::query()
                ->chunkById(100, function ($notifications) use (&$count): void {
                    foreach ($notifications as $notification) {
                        $this->deleteNotification($notification);
                        $count++;
                    }
                }, 'admin_notification_id', 'admin_notification_id');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to clear notifications.',
                'summary' => $this->summary(),
            ], 500);
        }

        return response()->json([
            'message' => $count > 0 ? "{$count} notifications cleared." : 'No notifications to clear.',
            'summary' => $this->summary(),
        ]);
    }

    private function filteredQuery(string $filter)
    {
        $query = $this->baseNotificationQuery();

        return match ($filter) {
            'unread' => $this->applyUnread($query),
            'read' => $this->applyRead($query),
            'donor_registration' => $this->hasColumn('notification_type') ? $query->whereIn('notification_type', ['donor_registration', 'new_donor_registration']) : $query,
            'appointment' => $this->hasColumn('notification_type') ? $query->where(function ($builder): void {
                $builder->where('notification_type', 'appointment')
                    ->orWhere('notification_type', 'like', 'appointment_%');
            }) : $query,
            'donation' => $this->hasColumn('notification_type') ? $query->whereIn('notification_type', ['donation', 'donation_completed', 'donation_deferred', 'appointment_completed', 'appointment_deferred_on_site']) : $query,
            'blood_stock_alert' => $this->hasColumn('notification_type') ? $query->whereIn('notification_type', ['blood_stock_alert', 'low_blood_stock_alert']) : $query,
            'low_stock' => $this->hasColumn('notification_type') ? $query->whereIn('notification_type', ['blood_stock_alert', 'low_blood_stock_alert', 'facility_low_stock', 'facility_out_of_stock', 'facility_stock_recovered']) : $query,
            'blood_request' => $this->hasColumn('notification_type') ? $query->where('notification_type', 'like', 'blood_request%') : $query,
            'donor_verification' => $this->hasColumn('notification_type') ? $query->where('notification_type', 'like', 'donor_verification%') : $query,
            'eligibility' => $this->hasColumn('notification_type') ? $query->whereIn('notification_type', ['eligibility_submitted', 'eligibility_reviewed', 'eligibility_auto_evaluated']) : $query,
            'event' => $this->hasColumn('notification_type') ? $query->where('notification_type', 'like', 'donation_event%') : $query,
            'report' => $this->hasColumn('notification_type') ? $query->whereIn('notification_type', ['report', 'monthly_report_generated', 'report_generated']) : $query,
            'system' => $this->hasColumn('notification_type') ? $query->whereIn('notification_type', ['system', 'admin', 'announcement']) : $query,
            default => $query,
        };
    }

    private function unreadQuery()
    {
        return $this->applyUnread($this->baseNotificationQuery());
    }

    private function applyUnread($query)
    {
        if ($this->hasColumn('read_at') && $this->hasColumn('is_read')) {
            return $query->where(function ($builder): void {
                $builder->whereNull('read_at')
                    ->where(function ($inner): void {
                        $inner->where('is_read', 0)
                            ->orWhereNull('is_read');
                    });
            });
        }

        if ($this->hasColumn('read_at')) {
            return $query->whereNull('read_at');
        }

        if ($this->hasColumn('is_read')) {
            return $query->where(function ($builder): void {
                $builder->where('is_read', 0)
                    ->orWhereNull('is_read');
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function applyRead($query)
    {
        if ($this->hasColumn('read_at') && $this->hasColumn('is_read')) {
            return $query->where(function ($builder): void {
                $builder->whereNotNull('read_at')
                    ->orWhere('is_read', 1);
            });
        }

        if ($this->hasColumn('read_at')) {
            return $query->whereNotNull('read_at');
        }

        if ($this->hasColumn('is_read')) {
            return $query->where('is_read', 1);
        }

        return $query->whereRaw('1 = 0');
    }

    private function summary(): array
    {
        if (! $this->hasAdminNotificationsTable()) {
            return $this->emptySummary();
        }

        try {
            $total = (int) $this->baseNotificationQuery()->count();
            $unread = (int) $this->unreadQuery()->count();
        } catch (Throwable $exception) {
            report($exception);

            return $this->emptySummary();
        }

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => max(0, $total - $unread),
        ];
    }

    private function emptyPayload(int $page = 1, int $perPage = 50): array
    {
        return [
            'data' => [],
            'summary' => $this->emptySummary(),
            'meta' => [
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'unread' => 0,
            'read' => 0,
        ];
    }

    private function baseNotificationQuery()
    {
        return AdminNotification::query();
    }

    private function findNotification(int $id): ?AdminNotification
    {
        if (! $this->hasAdminNotificationsTable()) {
            return null;
        }

        try {
            return $this->baseNotificationQuery()
                ->where('admin_notification_id', $id)
                ->first();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function deleteNotification(AdminNotification $notification): void
    {
        $notification->delete();
    }

    private function hasAdminNotificationsTable(): bool
    {
        return $this->hasTable('admin_notifications');
    }

    private function hasTable(string $table): bool
    {
        static $tables = [];

        if (array_key_exists($table, $tables)) {
            return $tables[$table];
        }

        try {
            return $tables[$table] = Schema::hasTable($table);
        } catch (Throwable $exception) {
            report($exception);

            return $tables[$table] = false;
        }
    }

    private function hasColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = [];

            if (! $this->hasAdminNotificationsTable()) {
                return false;
            }

            try {
                foreach (Schema::getColumnListing('admin_notifications') as $existingColumn) {
                    $columns[$existingColumn] = true;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return isset($columns[$column]);
    }

    private function transform(AdminNotification $notification, bool $withDetails = false): array
    {
        $id = (int) $notification->admin_notification_id;
        $type = (string) ($notification->notification_type ?: 'system');
        $isRead = (bool) ($notification->is_read || $notification->read_at);
        $createdAt = $this->dateValue($notification->created_at);
        $readAt = $this->dateValue($notification->read_at);

        $data = [
            'id' => $id,
            'admin_notification_id' => $id,
            'type' => $type,
            'notification_type' => $type,
            'title' => (string) ($notification->title ?: $this->notificationService->titleFromType($type)),
            'message' => (string) ($notification->message ?? ''),
            'channel' => (string) ($notification->channel ?: 'system'),
            'is_read' => $isRead,
            'read_at' => $readAt?->toIso8601String(),
            'created_at' => $createdAt?->toIso8601String(),
            'created_at_human' => $createdAt ? $createdAt->diffForHumans() : '',
            'created_at_display' => $createdAt ? $createdAt->format('M j, Y g:i A') : '-',
            'related_type' => $notification->related_type,
            'related_id' => $notification->related_id ? (int) $notification->related_id : null,
            'related' => $this->relatedLink($notification),
        ];

        if ($withDetails) {
            $data['status_label'] = $isRead ? 'Read' : 'Unread';
            $data['read_at_display'] = $readAt ? $readAt->format('M j, Y g:i A') : null;
        }

        return $data;
    }

    private function dateValue(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function relatedLink(AdminNotification $notification): ?array
    {
        $type = strtolower((string) $notification->related_type);
        $id = $notification->related_id ? (int) $notification->related_id : null;

        if ($type === '' || $id === null) {
            return null;
        }

        if ($type === 'donor' && Route::has('admin.users.show')) {
            return ['label' => 'Open donor record', 'url' => route('admin.users.show', $id)];
        }

        if ($type === 'appointment' && Route::has('admin.appointments')) {
            return ['label' => 'Open appointments', 'url' => route('admin.appointments') . '?search=AP' . str_pad((string) $id, 3, '0', STR_PAD_LEFT)];
        }

        if ($type === 'donation' && Route::has('admin.donation-records')) {
            return ['label' => 'Open donation records', 'url' => route('admin.donation-records') . '?search=' . $id];
        }

        if ($type === 'eligibility' && Route::has('admin.eligibility.index')) {
            return ['label' => 'Open eligibility review', 'url' => route('admin.eligibility.index')];
        }

        if ($type === 'report' && Route::has('admin.report-analytics')) {
            return ['label' => 'Open reports', 'url' => route('admin.report-analytics')];
        }

        if ($type === 'facility' && Route::has('admin.facilities.index')) {
            return ['label' => 'Open facilities', 'url' => route('admin.facilities.index')];
        }

        if ($type === 'blood_request' && Route::has('admin.blood-requests.show')) {
            return ['label' => 'Open blood request', 'url' => route('admin.blood-requests.show', $id)];
        }

        if ($type === 'event' && Route::has('admin.donation-events.show')) {
            return ['label' => 'Open donation event', 'url' => route('admin.donation-events.show', $id)];
        }

        return null;
    }

    private function filterOptions(): array
    {
        return collect(self::FILTERS)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function typeOptions(): array
    {
        return collect(self::TYPES)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
