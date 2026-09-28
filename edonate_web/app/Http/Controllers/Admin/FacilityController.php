<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Services\AdminNotificationService;
use App\Services\FacilityBloodInventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class FacilityController extends Controller
{
    public function __construct(
        private readonly FacilityBloodInventoryService $inventoryService,
        private readonly AdminNotificationService $adminNotificationService
    ) {}

    public function index(Request $request)
    {
        return view('admin.facilities', [
            'facilityTypes' => $this->inventoryService->facilityTypes(),
            'canManage' => $this->isAdmin($request),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'facility_type' => ['nullable', Rule::in(array_merge([''], $this->inventoryService->facilityTypes()))],
            'status' => ['nullable', Rule::in(['', 'active', 'inactive'])],
        ]);

        $query = Facility::query()->with(['inventories.bloodType']);
        $search = trim((string) ($validated['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('facility_name', 'like', $like)
                    ->orWhere('barangay_name', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('province', 'like', $like);
            });
        }

        if (! empty($validated['facility_type'])) {
            $query->where('facility_type', $validated['facility_type']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $paginator = $query->orderBy('facility_name')->paginate(
            (int) ($validated['per_page'] ?? 10),
            ['*'],
            'page',
            (int) ($validated['page'] ?? 1)
        );

        $bloodTypeIds = $this->inventoryService->bloodTypes()->pluck('blood_type_id');
        $rows = $paginator->getCollection()->map(function (Facility $facility) use ($bloodTypeIds): array {
            $inventory = $facility->inventories;
            $totalUnits = (int) $inventory->sum('available_units');
            $byBloodType = $inventory->keyBy('blood_type_id');
            $lowStock = $bloodTypeIds->filter(function ($bloodTypeId) use ($byBloodType): bool {
                $row = $byBloodType->get($bloodTypeId);

                return $this->inventoryService->inventoryStatus(
                    (int) ($row?->available_units ?? 0),
                    (int) ($row?->low_stock_threshold ?? $this->inventoryService->defaultThreshold())
                ) !== 'available';
            })->count();

            return array_merge($this->inventoryService->facilityData($facility), [
                'total_available_units' => $totalUnits,
                'low_or_out_types' => $lowStock,
                'inventory_url' => route('admin.facilities.inventory', $facility),
            ]);
        })->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'summary' => [
                'total' => Facility::query()->count(),
                'active' => Facility::query()->where('status', 'active')->count(),
                'inactive' => Facility::query()->where('status', 'inactive')->count(),
                'mapped' => Facility::query()->get()->filter(fn (Facility $facility): bool => $this->inventoryService->facilityData($facility)['mapped'])->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $this->validatedFacility($request);
        try {
            $facility = DB::transaction(fn () => Facility::query()->create($attributes));
        } catch (QueryException $exception) {
            report($exception);

            return $this->saveFailure();
        }
        $this->audit($request, 'facility_created', 'Created facility '.$facility->facility_name.'.', $facility, [
            'facility_type' => $facility->facility_type,
            'status' => $facility->status,
        ]);

        return response()->json([
            'message' => 'Facility created.',
            'facility' => $this->inventoryService->facilityData($facility),
        ], 201);
    }

    public function update(Request $request, Facility $facility): JsonResponse
    {
        $before = $this->inventoryService->facilityData($facility);
        $attributes = $this->validatedFacility($request);
        try {
            DB::transaction(fn () => $facility->fill($attributes)->save());
        } catch (QueryException $exception) {
            report($exception);

            return $this->saveFailure();
        }
        $this->audit($request, 'facility_updated', 'Updated facility '.$facility->facility_name.'.', $facility, [
            'before' => $before,
            'after' => $this->inventoryService->facilityData($facility),
        ]);

        return response()->json([
            'message' => 'Facility updated.',
            'facility' => $this->inventoryService->facilityData($facility),
        ]);
    }

    public function status(Request $request, Facility $facility): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        $previous = (string) $facility->status;
        $facility->status = $validated['status'];
        $facility->save();

        $this->audit($request, 'facility_status_changed', 'Changed facility status to '.$facility->status.'.', $facility, [
            'previous_status' => $previous,
            'new_status' => $facility->status,
        ]);

        return response()->json(['message' => 'Facility status updated.']);
    }

    public function inventory(Request $request, Facility $facility)
    {
        return view('admin.facility_inventory', [
            'facility' => $facility,
            'canManage' => $this->isAdmin($request),
        ]);
    }

    public function inventoryData(Facility $facility): JsonResponse
    {
        return response()->json($this->inventoryService->inventoryDetails($facility));
    }

    public function updateInventory(Request $request, Facility $facility): JsonResponse
    {
        $validated = $request->validate([
            'inventory' => ['required', 'array', 'min:1', 'max:8'],
            'inventory.*.blood_type_id' => ['required', 'integer', 'distinct', 'exists:blood_types,blood_type_id'],
            'inventory.*.available_units' => ['required', 'integer', 'min:0', 'max:1000000'],
            'inventory.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $adminId = (int) $request->session()->get('admin_id');
        $changes = $this->inventoryService->setInventory(
            $facility,
            $validated['inventory'],
            $adminId,
            trim($validated['reason'])
        );

        if ($changes !== []) {
            $this->audit($request, 'blood_inventory_updated', 'Updated blood inventory for '.$facility->facility_name.'.', $facility, [
                'changes' => $changes,
                'reason' => trim($validated['reason']),
            ], 'facility_blood_inventory');

            $this->notifyInventoryStateChanges($facility, $changes);
        }

        return response()->json([
            'message' => $changes === [] ? 'No inventory values changed.' : 'Inventory updated.',
            'changes' => $changes,
            'data' => $this->inventoryService->inventoryDetails($facility->fresh()),
        ]);
    }

    public function mapData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blood_type' => ['nullable', Rule::in(array_merge([''], $this->inventoryService->bloodTypeNames()))],
            'facility_type' => ['nullable', Rule::in(array_merge([''], $this->inventoryService->facilityTypes()))],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        return response()->json($this->inventoryService->getMapData($validated));
    }

    /** @return array<string, mixed> */
    private function validatedFacility(Request $request): array
    {
        $input = $request->validate([
            'facility_name' => ['required', 'string', 'max:150'],
            'facility_type' => ['required', Rule::in($this->inventoryService->facilityTypes())],
            'address' => ['nullable', 'string', 'max:5000'],
            'barangay_name' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'location_pin_selected' => ['accepted'],
            'latitude' => ['required_if:location_pin_selected,1', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:location_pin_selected,1', 'nullable', 'numeric', 'between:-180,180'],
            'location_confirmed' => ['accepted'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'location_confirmed.accepted' => 'Confirm that you have reviewed the facility address and its components.',
            'location_pin_selected.accepted' => 'Choose a position by clicking the map or dragging the facility pin.',
            'latitude.required_if' => 'Choose a facility position on the map before saving.',
            'longitude.required_if' => 'Choose a facility position on the map before saving.',
            'city.required' => 'Enter the city or municipality.',
            'province.required' => 'Enter the province.',
        ]);
        $attributes = collect($input)->only([
            'facility_name', 'facility_type', 'address', 'barangay_name', 'city', 'province', 'latitude', 'longitude', 'contact_number', 'status',
        ])->all();

        if ((float) ($input['latitude'] ?? 0) === 0.0 && (float) ($input['longitude'] ?? 0) === 0.0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'location_pin_selected' => 'Choose a valid position on the map before saving.',
            ]);
        }

        return $attributes;
    }

    private function saveFailure(): JsonResponse
    {
        return response()->json(['message' => 'The facility could not be saved. Please contact the system administrator if this continues.'], 500);
    }

    private function isAdmin(Request $request): bool
    {
        return Str::lower((string) $request->session()->get('admin_role')) === 'admin';
    }

    /**
     * Create one admin notification only when an inventory status changes.
     * Re-saving the same low/out-of-stock value therefore does not spam staff.
     *
     * @param  array<int, array<string, mixed>>  $changes
     */
    private function notifyInventoryStateChanges(Facility $facility, array $changes): void
    {
        $bloodTypes = $this->inventoryService->bloodTypes()->keyBy('blood_type_id');

        foreach ($changes as $change) {
            $previous = $this->inventoryService->inventoryStatus(
                (int) ($change['previous_units'] ?? 0),
                (int) ($change['previous_threshold'] ?? $this->inventoryService->defaultThreshold())
            );
            $current = $this->inventoryService->inventoryStatus(
                (int) ($change['new_units'] ?? 0),
                (int) ($change['new_threshold'] ?? $this->inventoryService->defaultThreshold())
            );

            if ($previous === $current) {
                continue;
            }

            $bloodType = (string) ($bloodTypes->get((int) ($change['blood_type_id'] ?? 0))?->blood_type ?? 'blood type');
            $statusLabel = match ($current) {
                'out_of_stock' => 'out of stock',
                'low' => 'low',
                default => 'available again',
            };
            $type = match ($current) {
                'out_of_stock' => 'facility_out_of_stock',
                'low' => 'facility_low_stock',
                default => 'facility_stock_recovered',
            };

            $this->adminNotificationService->createAdminEvent(
                $type,
                $this->adminNotificationService->titleFromType($type),
                $bloodType.' stock at '.$facility->facility_name.' changed from '.str_replace('_', ' ', $previous).' to '.$statusLabel.'.',
                'facility',
                (int) $facility->facility_id
            );
        }
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Request $request, string $action, string $description, Facility $facility, array $metadata, string $targetTable = 'facilities'): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        try {
            DB::table('audit_logs')->insert([
                'actor_admin_id' => is_numeric($request->session()->get('admin_id')) ? (int) $request->session()->get('admin_id') : null,
                'actor_name' => trim((string) ($request->session()->get('admin_full_name') ?: $request->session()->get('admin_username') ?: 'Admin')),
                'actor_role' => ucfirst(Str::lower((string) $request->session()->get('admin_role', 'admin'))),
                'action_type' => $action,
                'module_type' => 'facility_inventory',
                'target_table' => $targetTable,
                'target_id' => (int) $facility->facility_id,
                'description' => Str::limit($description, 255, ''),
                'ip_address' => $request->ip(),
                'result' => 'success',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
