<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonationEvent;
use App\Models\Facility;
use App\Services\AppointmentBookingService;
use App\Services\AdminNotificationService;
use App\Services\DonationEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Throwable;

class DonationEventController extends Controller
{
    public function __construct(
        private readonly AppointmentBookingService $bookingService,
        private readonly DonationEventService $eventService,
        private readonly AdminNotificationService $adminNotificationService
    )
    {
    }

    public function index()
    {
        return view('admin.donation_events', [
            'donationEventPayload' => [
                'api' => [
                    'listUrl' => route('admin.donation-events.data'),
                    'storeUrl' => route('admin.donation-events.store'),
                    'facilities' => $this->facilityOptions(),
                ],
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['', 'open', 'closed', 'completed', 'cancelled'])],
        ]);

        $query = DonationEvent::query()->with('creator');
        if ($this->eventFacilityAvailable()) {
            $query->with('facility');
        }
        $search = trim((string) ($validated['search'] ?? ''));
        $status = Str::lower(trim((string) ($validated['status'] ?? '')));

        if ($search !== '') {
            $likeTerm = '%' . $search . '%';
            $query->where(function ($builder) use ($likeTerm): void {
                $builder->where('title', 'like', $likeTerm)
                    ->orWhere('location_name', 'like', $likeTerm)
                    ->orWhere('address', 'like', $likeTerm);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if (! empty($validated['date'])) {
            $query->whereDate('event_date', $validated['date']);
        }

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $paginator = $query
            ->orderByDesc('event_date')
            ->orderByDesc('start_time')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(function (DonationEvent $event): array {
                    $payload = $this->eventResponse($event);
                    $payload['actions_html'] = view('admin.partials.donation-event-actions', [
                        'event' => $event,
                    ])->render();

                    return $payload;
                })
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'stats' => $this->statusStats(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPayload($request, null, true);
        $payload['created_by_admin_id'] = is_numeric($request->session()->get('admin_id'))
            ? (int) $request->session()->get('admin_id')
            : null;

        $event = $this->eventService->create($payload);

        $this->logEventAudit($request, 'donation_event_created', "Created donation event {$event->title}.", (int) $event->event_id, [
            'event_id' => (int) $event->event_id,
            'status' => (string) $event->status,
        ]);

        $this->adminNotificationService->createAdminEvent(
            'donation_event_created',
            'Donation Event Created',
            "Donation event {$event->title} was created.",
            'event',
            (int) $event->event_id
        );

        return response()->json([
            'message' => 'Donation event created.',
            'event' => $this->eventResponse($event),
        ], 201);
    }

    public function show(Request $request, DonationEvent $event)
    {
        if (! $request->expectsJson() && ! $request->ajax()) {
            return $this->bookings($request, $event);
        }

        return response()->json([
            'event' => $this->eventResponse($event->loadMissing('creator')),
        ]);
    }

    public function update(Request $request, DonationEvent $event): JsonResponse
    {
        $before = $this->eventResponse($event);
        try {
            $event = $this->eventService->update(
                $event,
                $this->validatedPayload($request, $event, false)
            );
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->logEventAudit($request, 'donation_event_updated', "Updated donation event {$event->title}.", (int) $event->event_id, [
            'event_id' => (int) $event->event_id,
            'before' => $before,
            'after' => $this->eventResponse($event),
        ]);

        $this->adminNotificationService->createAdminEvent(
            'donation_event_updated',
            'Donation Event Updated',
            "Donation event {$event->title} was updated.",
            'event',
            (int) $event->event_id
        );

        return response()->json([
            'message' => 'Donation event updated.',
            'event' => $this->eventResponse($event),
        ]);
    }

    public function destroy(): JsonResponse
    {
        return response()->json([
            'message' => 'Donation events are retained for audit history. Close or cancel the event instead.',
        ], 405);
    }

    public function open(Request $request, DonationEvent $event): JsonResponse
    {
        return $this->changeStatus($request, $event, 'open', 'Donation event opened.');
    }

    public function close(Request $request, DonationEvent $event): JsonResponse
    {
        return $this->changeStatus($request, $event, 'closed', 'Donation event closed.');
    }

    public function complete(Request $request, DonationEvent $event): JsonResponse
    {
        return $this->changeStatus($request, $event, 'completed', 'Donation event marked as completed.');
    }

    public function cancel(Request $request, DonationEvent $event): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->changeStatus($request, $event, 'cancelled', 'Donation event cancelled.', $validated['reason'] ?? null);
    }

    public function bookings(Request $request, DonationEvent $event)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'])],
        ]);

        $status = Str::lower(trim((string) ($validated['status'] ?? '')));
        $statusExpression = "CASE
            WHEN LOWER(COALESCE(ap.status, '')) IN ('confirmed', 'approved', 'scheduled', 'rescheduled') THEN 'confirmed'
            WHEN LOWER(COALESCE(ap.status, '')) IN ('checked_in', 'checked in') THEN 'checked_in'
            WHEN LOWER(COALESCE(ap.status, '')) IN ('completed', 'complete', 'done') THEN 'completed'
            WHEN LOWER(COALESCE(ap.status, '')) IN ('cancelled', 'canceled', 'rejected', 'declined') THEN 'cancelled'
            WHEN LOWER(COALESCE(ap.status, '')) IN ('no_show', 'no show', 'noshow') THEN 'no_show'
            ELSE 'pending'
        END";

        $query = DB::table('appointments as ap')
            ->leftJoin('donors as d', 'd.donor_id', '=', 'ap.donor_id')
            ->leftJoinSub(
                DB::table('donor_authentication')->select('donor_id', DB::raw('MAX(auth_id) as latest_auth_id'))->groupBy('donor_id'),
                'da_latest',
                fn ($join) => $join->on('da_latest.donor_id', '=', 'd.donor_id')
            )
            ->leftJoin('donor_authentication as da', 'da.auth_id', '=', 'da_latest.latest_auth_id')
            ->leftJoinSub(
                DB::table('eligibility_status')->select('donor_id', DB::raw('MAX(eligibility_id) as latest_eligibility_id'))->groupBy('donor_id'),
                'es_latest',
                fn ($join) => $join->on('es_latest.donor_id', '=', 'd.donor_id')
            )
            ->leftJoin('eligibility_status as es', 'es.eligibility_id', '=', 'es_latest.latest_eligibility_id')
            ->where('ap.event_id', $event->event_id)
            ->select([
                'ap.appointment_id',
                'ap.appointment_date',
                'ap.appointment_time',
                'ap.status',
                'ap.created_at',
                'd.donor_id',
                'd.first_name',
                'd.last_name',
                'd.verification_status',
                'da.email',
                'es.status as latest_eligibility_status',
            ])
            ->selectRaw("({$statusExpression}) as normalized_status");

        if ($status !== '') {
            $query->whereRaw("({$statusExpression}) = ?", [$status]);
        }

        $appointments = $query
            ->orderBy('ap.appointment_time')
            ->orderBy('ap.appointment_id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.donation_event_show', [
            'event' => $event->loadMissing('creator'),
            'eventPayload' => $this->eventResponse($event),
            'appointments' => $appointments,
            'filters' => ['status' => $status],
        ]);
    }

    private function validatedPayload(Request $request, ?DonationEvent $event, bool $creating): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:150'],
            'location_name' => ['required', 'string', 'max:150'],
            'facility_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['required', 'date', $creating ? 'after_or_equal:today' : 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'max_capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', Rule::in(['open', 'closed', 'completed', 'cancelled'])],
        ];

        if ($request->filled('facility_id')) {
            if (! $this->eventFacilityAvailable()) {
                throw ValidationException::withMessages([
                    'facility_id' => 'Facility assignment is unavailable until the latest database migrations are applied.',
                ]);
            }
            $rules['facility_id'][] = Rule::exists('facilities', 'facility_id');
        }

        $validated = $request->validate($rules);
        if (! $this->eventFacilityAvailable()) {
            unset($validated['facility_id']);
        } elseif (($validated['facility_id'] ?? null) === '') {
            $validated['facility_id'] = null;
        }

        if ($event instanceof DonationEvent && (int) $validated['max_capacity'] < $this->eventService->bookedSlotCount((int) $event->event_id)) {
            throw ValidationException::withMessages([
                'max_capacity' => 'The capacity cannot be lower than the number of currently confirmed appointments.',
            ]);
        }

        return $validated;
    }

    private function eventResponse(DonationEvent $event): array
    {
        if ($this->eventFacilityAvailable()) {
            $event->loadMissing('facility');
        }

        $payload = $this->bookingService->eventPayload($event);
        $payload['created_by'] = $event->relationLoaded('creator')
            ? ($event->creator?->full_name ?: $event->creator?->username)
            : DB::table('admins')->where('admin_id', $event->created_by_admin_id)->value('full_name');

        return $payload;
    }

    /** @return array<int, array{id: int, name: string}> */
    private function facilityOptions(): array
    {
        if (! Schema::hasColumns('facilities', ['facility_id', 'facility_name'])) {
            return [];
        }

        $query = DB::table('facilities');
        if (Schema::hasColumn('facilities', 'status')) {
            $query->whereRaw("LOWER(TRIM(COALESCE(status, 'active'))) = 'active'");
        }

        return $query->orderBy('facility_name')->get(['facility_id', 'facility_name'])
            ->map(static fn (object $facility): array => [
                'id' => (int) $facility->facility_id,
                'name' => trim((string) $facility->facility_name),
            ])->all();
    }

    private function eventFacilityAvailable(): bool
    {
        return Schema::hasTable('facilities')
            && Schema::hasColumn('donation_events', 'facility_id')
            && Schema::hasColumns('facilities', ['facility_id', 'facility_name']);
    }

    private function statusStats(): array
    {
        $stats = [
            'open' => 0,
            'closed' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'total_confirmed_bookings' => $this->totalConfirmedBookings(),
            'full' => 0,
        ];

        DonationEvent::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->each(function (object $row) use (&$stats): void {
                $status = $this->bookingService->normalizeEventStatus((string) ($row->status ?? ''));
                if (isset($stats[$status])) {
                    $stats[$status] += (int) ($row->total ?? 0);
                }
            });

        DonationEvent::query()->get()->each(function (DonationEvent $event) use (&$stats): void {
            if ($this->bookingService->eventPayload($event)['availability_status'] === 'full') {
                $stats['full']++;
            }
        });

        return $stats;
    }

    private function totalConfirmedBookings(): int
    {
        return (int) DB::table('appointments')
            ->whereNotNull('event_id')
            ->whereRaw("LOWER(COALESCE(status, '')) IN ('confirmed','approved','scheduled','rescheduled','checked_in','checked in','completed','complete','done')")
            ->count();
    }

    private function changeStatus(
        Request $request,
        DonationEvent $event,
        string $status,
        string $message,
        ?string $reason = null
    ): JsonResponse {
        $before = $this->eventResponse($event);

        try {
            $updated = $this->eventService->changeStatus($event, $status, $reason);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->logEventAudit($request, 'donation_event_' . $status, $message, (int) $updated->event_id, [
            'event_id' => (int) $updated->event_id,
            'before' => $before,
            'after' => $this->eventResponse($updated),
            'reason' => $reason,
        ]);

        $this->adminNotificationService->createAdminEvent(
            'donation_event_' . $status,
            'Donation Event Updated',
            "Donation event {$updated->title} was {$status}." . ($reason ? " Reason: {$reason}" : ''),
            'event',
            (int) $updated->event_id
        );

        return response()->json([
            'message' => $message,
            'event' => $this->eventResponse($updated),
        ]);
    }

    private function logEventAudit(Request $request, string $actionType, string $description, int $eventId, array $metadata = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        try {
            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => trim((string) ($request->session()->get('admin_full_name') ?: $request->session()->get('admin_username') ?: 'Admin')),
                'actor_role' => ucfirst(Str::lower((string) $request->session()->get('admin_role', 'admin'))),
                'action_type' => $actionType,
                'module_type' => 'donation_events',
                'target_table' => 'donation_events',
                'target_id' => $eventId,
                'description' => $description,
                'ip_address' => $request->ip(),
                'result' => 'success',
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
