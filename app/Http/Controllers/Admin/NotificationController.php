<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

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
        'report' => 'Report',
        'system' => 'System',
    ];

    private const TYPES = [
        'system' => 'System',
        'donor_registration' => 'Donor Registration',
        'appointment_booked' => 'Appointment',
        'donation_completed' => 'Donation',
        'blood_stock_alert' => 'Blood Stock Alert',
        'report' => 'Report',
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
                    ['value' => 'push', 'label' => 'Push'],
                ],
                'recipients' => [
                    ['value' => 'system', 'label' => 'System-wide'],
                    ['value' => 'admin', 'label' => 'Admins only'],
                    ['value' => 'all_donors', 'label' => 'All donors'],
                    ['value' => 'donor', 'label' => 'Specific donor ID'],
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

        $query = $this->filteredQuery($filter)
            ->with('donor')
            ->orderByDesc('created_at')
            ->orderByDesc('notification_id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Notification $notification): array => $this->transform($notification))
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

    public function show(Notification $notification): JsonResponse
    {
        $notification->load('donor');

        return response()->json([
            'data' => $this->transform($notification, true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'channel' => ['required', 'string', Rule::in(['system', 'email', 'push'])],
            'recipient_type' => ['required', 'string', Rule::in(['system', 'admin', 'all_donors', 'donor'])],
            'recipient_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $recipientType = (string) $validated['recipient_type'];
        $created = collect();

        if ($recipientType === 'donor') {
            $donorId = (int) ($validated['recipient_id'] ?? 0);
            if ($donorId <= 0 || ! DB::table('donors')->where('donor_id', $donorId)->exists()) {
                return response()->json([
                    'message' => 'Please enter a valid donor ID.',
                    'errors' => ['recipient_id' => ['Please enter a valid donor ID.']],
                ], 422);
            }

            $created->push($this->notificationService->create(array_merge($validated, [
                'donor_id' => $donorId,
                'recipient_id' => $donorId,
                'related_type' => 'donor',
                'related_id' => $donorId,
            ])));
        } elseif ($recipientType === 'all_donors') {
            $donorIds = DB::table('donors')->orderBy('donor_id')->pluck('donor_id');
            foreach ($donorIds as $donorId) {
                $created->push($this->notificationService->create(array_merge($validated, [
                    'donor_id' => (int) $donorId,
                    'recipient_id' => (int) $donorId,
                    'related_type' => 'donor',
                    'related_id' => (int) $donorId,
                ])));
            }

            if ($donorIds->isEmpty()) {
                $created->push($this->notificationService->create($validated));
            }
        } else {
            $created->push($this->notificationService->create($validated));
        }

        $created = $created->filter();
        if ($created->isEmpty()) {
            return response()->json(['message' => 'Unable to create notification.'], 500);
        }

        /** @var Notification $first */
        $first = $created->first();
        $first->load('donor');

        return response()->json([
            'message' => $created->count() > 1
                ? "Created {$created->count()} notifications."
                : 'Notification created.',
            'data' => $this->transform($first, true),
            'summary' => $this->summary(),
        ], 201);
    }

    public function markRead(Notification $notification): JsonResponse
    {
        $notification->forceFill([
            'is_read' => 1,
            'read_at' => $notification->read_at ?: now(),
            'updated_at' => now(),
        ])->save();

        $notification->load('donor');

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $this->transform($notification, true),
            'summary' => $this->summary(),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $count = 0;

        $this->unreadQuery()
            ->chunkById(100, function ($notifications) use (&$count): void {
                foreach ($notifications as $notification) {
                    $notification->forceFill([
                        'is_read' => 1,
                        'read_at' => $notification->read_at ?: now(),
                        'updated_at' => now(),
                    ])->save();
                    $count++;
                }
            }, 'notification_id');

        return response()->json([
            'message' => $count > 0 ? "{$count} notifications marked as read." : 'No unread notifications to update.',
            'summary' => $this->summary(),
        ]);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted.',
            'summary' => $this->summary(),
        ]);
    }

    public function clearAll(): JsonResponse
    {
        $count = 0;

        Notification::query()
            ->chunkById(100, function ($notifications) use (&$count): void {
                foreach ($notifications as $notification) {
                    $notification->delete();
                    $count++;
                }
            }, 'notification_id');

        return response()->json([
            'message' => $count > 0 ? "{$count} notifications cleared." : 'No notifications to clear.',
            'summary' => $this->summary(),
        ]);
    }

    private function filteredQuery(string $filter)
    {
        $query = Notification::query();

        return match ($filter) {
            'unread' => $this->applyUnread($query),
            'read' => $this->applyRead($query),
            'donor_registration' => $query->whereIn('notification_type', ['donor_registration', 'new_donor_registration']),
            'appointment' => $query->where('notification_type', 'like', 'appointment_%'),
            'donation' => $query->whereIn('notification_type', ['donation_completed', 'appointment_completed']),
            'blood_stock_alert' => $query->whereIn('notification_type', ['blood_stock_alert', 'low_blood_stock_alert']),
            'report' => $query->whereIn('notification_type', ['report', 'monthly_report_generated', 'report_generated']),
            'system' => $query->whereIn('notification_type', ['system', 'admin', 'announcement']),
            default => $query,
        };
    }

    private function unreadQuery()
    {
        return $this->applyUnread(Notification::query());
    }

    private function applyUnread($query)
    {
        return $query->where(function ($builder): void {
            $builder->whereNull('read_at')
                ->where(function ($inner): void {
                    $inner->where('is_read', 0)
                        ->orWhereNull('is_read');
                });
        });
    }

    private function applyRead($query)
    {
        return $query->where(function ($builder): void {
            $builder->whereNotNull('read_at')
                ->orWhere('is_read', 1);
        });
    }

    private function summary(): array
    {
        $total = (int) Notification::query()->count();
        $unread = (int) $this->unreadQuery()->count();

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => max(0, $total - $unread),
        ];
    }

    private function transform(Notification $notification, bool $withDetails = false): array
    {
        $type = (string) ($notification->notification_type ?: 'system');
        $isRead = (bool) ($notification->is_read || $notification->read_at);
        $createdAt = $notification->created_at instanceof Carbon
            ? $notification->created_at
            : ($notification->created_at ? Carbon::parse($notification->created_at) : null);
        $readAt = $notification->read_at instanceof Carbon
            ? $notification->read_at
            : ($notification->read_at ? Carbon::parse($notification->read_at) : null);

        $data = [
            'id' => (int) $notification->notification_id,
            'type' => $type,
            'title' => (string) ($notification->title ?: $this->notificationService->titleFromType($type)),
            'message' => (string) ($notification->message ?? ''),
            'channel' => (string) ($notification->channel ?: 'system'),
            'recipient_type' => (string) ($notification->recipient_type ?: ($notification->donor_id ? 'donor' : 'system')),
            'recipient_id' => $notification->recipient_id ? (int) $notification->recipient_id : null,
            'donor_id' => $notification->donor_id ? (int) $notification->donor_id : null,
            'donor_name' => $notification->donor
                ? trim((string) $notification->donor->first_name . ' ' . (string) $notification->donor->last_name)
                : null,
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

    private function relatedLink(Notification $notification): ?array
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
