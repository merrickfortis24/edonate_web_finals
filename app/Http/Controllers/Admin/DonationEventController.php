<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BloodType;
use App\Models\DonationEvent;
use App\Services\AppointmentBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class DonationEventController extends Controller
{
    public function __construct(private readonly AppointmentBookingService $bookingService)
    {
    }

    public function index()
    {
        return view('admin.donation_events', [
            'donationEventPayload' => [
                'api' => [
                    'listUrl' => route('admin.donation-events.data'),
                    'storeUrl' => route('admin.donation-events.store'),
                ],
                'bloodTypes' => BloodType::query()
                    ->orderBy('blood_type')
                    ->pluck('blood_type')
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', Rule::in(['', 'upcoming', 'ongoing', 'completed', 'cancelled'])],
        ]);

        $query = DonationEvent::query();
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
            $query->whereIn('status', $this->storageStatusesFor($status));
        }

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $paginator = $query
            ->orderByDesc('event_date')
            ->orderByDesc('start_time')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (DonationEvent $event): array => $this->eventResponse($event))
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
        $payload = $this->validatedPayload($request);
        $payload['created_by_admin_id'] = is_numeric($request->session()->get('admin_id'))
            ? (int) $request->session()->get('admin_id')
            : null;

        $event = DonationEvent::query()->create($payload);

        $this->logEventAudit($request, 'donation_event_created', "Created donation event {$event->title}.", (int) $event->event_id, [
            'event_id' => (int) $event->event_id,
            'status' => (string) $event->status,
        ]);

        return response()->json([
            'message' => 'Donation event created.',
            'event' => $this->eventResponse($event),
        ], 201);
    }

    public function show(DonationEvent $event): JsonResponse
    {
        return response()->json([
            'event' => $this->eventResponse($event),
        ]);
    }

    public function update(Request $request, DonationEvent $event): JsonResponse
    {
        $before = $this->eventResponse($event);
        $event->fill($this->validatedPayload($request));
        $event->save();

        $this->logEventAudit($request, 'donation_event_updated', "Updated donation event {$event->title}.", (int) $event->event_id, [
            'event_id' => (int) $event->event_id,
            'before' => $before,
            'after' => $this->eventResponse($event),
        ]);

        return response()->json([
            'message' => 'Donation event updated.',
            'event' => $this->eventResponse($event),
        ]);
    }

    public function destroy(Request $request, DonationEvent $event): JsonResponse
    {
        $eventId = (int) $event->event_id;
        $title = (string) $event->title;
        $event->delete();

        $this->logEventAudit($request, 'donation_event_deleted', "Deleted donation event {$title}.", $eventId, [
            'event_id' => $eventId,
            'title' => $title,
        ]);

        return response()->json([
            'message' => 'Donation event deleted.',
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'max_capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'blood_types_needed' => ['nullable', 'array'],
            'blood_types_needed.*' => ['string', 'max:5'],
            'status' => ['required', Rule::in(['upcoming', 'ongoing', 'completed', 'cancelled', 'open', 'closed'])],
        ]);

        $validated['status'] = $this->storageStatus((string) $validated['status']);
        $validated['blood_types_needed'] = json_encode($this->cleanBloodTypes($validated['blood_types_needed'] ?? []));

        return $validated;
    }

    private function eventResponse(DonationEvent $event): array
    {
        return $this->bookingService->eventPayload($event);
    }

    private function statusStats(): array
    {
        $stats = [
            'upcoming' => 0,
            'ongoing' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        DonationEvent::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->each(function (object $row) use (&$stats): void {
                $status = $this->bookingService->normalizeEventStatus((string) ($row->status ?? ''));
                $stats[$status] = ($stats[$status] ?? 0) + (int) ($row->total ?? 0);
            });

        return $stats;
    }

    private function storageStatus(string $status): string
    {
        $status = Str::lower(trim($status));

        return match ($status) {
            'open' => 'upcoming',
            'closed' => 'completed',
            'ongoing' => 'ongoing',
            'completed' => 'completed',
            'cancelled', 'canceled' => 'cancelled',
            default => 'upcoming',
        };
    }

    private function storageStatusesFor(string $status): array
    {
        return match ($status) {
            'upcoming' => ['upcoming', 'open'],
            'completed' => ['completed', 'closed'],
            'cancelled' => ['cancelled', 'canceled'],
            'ongoing' => ['ongoing'],
            default => [$this->storageStatus($status)],
        };
    }

    private function cleanBloodTypes(array $bloodTypes): array
    {
        return collect($bloodTypes)
            ->map(fn ($type): string => strtoupper(trim((string) $type)))
            ->filter(fn (string $type): bool => $type !== '')
            ->unique()
            ->values()
            ->all();
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
