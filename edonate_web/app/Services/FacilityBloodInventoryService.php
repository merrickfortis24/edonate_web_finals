<?php

namespace App\Services;

use App\Models\BloodType;
use App\Models\Facility;
use App\Models\FacilityBloodInventory;
use App\Models\FacilityBloodInventoryLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FacilityBloodInventoryService
{
    /** @var array<int, string> */
    private const SUPPORTED_BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /** @return array<int, string> */
    public function facilityTypes(): array
    {
        return Facility::TYPES;
    }

    /** @return Collection<int, BloodType> */
    public function bloodTypes(): Collection
    {
        $order = array_flip(self::SUPPORTED_BLOOD_TYPES);

        return BloodType::query()
            ->whereIn('blood_type', self::SUPPORTED_BLOOD_TYPES)
            ->get(['blood_type_id', 'blood_type'])
            ->sortBy(static fn (BloodType $type): int => $order[$type->blood_type] ?? 999)
            ->values();
    }

    /** @return array<int, string> */
    public function bloodTypeNames(): array
    {
        return $this->bloodTypes()->pluck('blood_type')->all();
    }

    public function defaultThreshold(): int
    {
        return max(0, (int) config('blood_inventory.default_low_stock_threshold', 5));
    }

    public function inventoryStatus(int $availableUnits, ?int $threshold = null): string
    {
        $threshold ??= $this->defaultThreshold();

        return match (true) {
            $availableUnits <= 0 => 'out_of_stock',
            $availableUnits <= max(0, $threshold) => 'low',
            default => 'available',
        };
    }

    /**
     * @return array{facility: array<string, mixed>, inventory: array<int, array<string, mixed>>, history: array<int, array<string, mixed>>}
     */
    public function inventoryDetails(Facility $facility): array
    {
        $facility->loadMissing(['inventories.bloodType', 'inventories.updatedBy']);
        $byBloodType = $facility->inventories->keyBy('blood_type_id');

        $inventory = $this->bloodTypes()->map(function (BloodType $bloodType) use ($byBloodType): array {
            /** @var FacilityBloodInventory|null $row */
            $row = $byBloodType->get($bloodType->blood_type_id);
            $units = max(0, (int) ($row?->available_units ?? 0));
            $threshold = max(0, (int) ($row?->low_stock_threshold ?? $this->defaultThreshold()));

            return [
                'inventory_id' => $row?->inventory_id ? (int) $row->inventory_id : null,
                'blood_type_id' => (int) $bloodType->blood_type_id,
                'blood_type' => (string) $bloodType->blood_type,
                'available_units' => $units,
                'low_stock_threshold' => $threshold,
                'status' => $this->inventoryStatus($units, $threshold),
                'last_updated' => $row?->last_updated?->toIso8601String(),
                'updated_by' => $row?->updatedBy?->full_name ?: $row?->updatedBy?->username,
            ];
        })->all();

        $history = FacilityBloodInventoryLog::query()
            ->where('facility_id', $facility->facility_id)
            ->with(['bloodType', 'updatedBy'])
            ->orderByDesc('inventory_log_id')
            ->limit(50)
            ->get()
            ->map(static fn (FacilityBloodInventoryLog $log): array => [
                'inventory_log_id' => (int) $log->inventory_log_id,
                'blood_type' => (string) ($log->bloodType?->blood_type ?? 'Unknown'),
                'previous_units' => (int) $log->previous_units,
                'new_units' => (int) $log->new_units,
                'change_amount' => (int) $log->change_amount,
                'action_type' => (string) $log->action_type,
                'reason' => (string) $log->reason,
                'updated_by' => $log->updatedBy?->full_name ?: $log->updatedBy?->username,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->all();

        return [
            'facility' => $this->facilityData($facility),
            'inventory' => $inventory,
            'history' => $history,
        ];
    }

    /**
     * @param array<int, array{blood_type_id: int, available_units: int, low_stock_threshold?: int|null}> $entries
     * @return array<int, array<string, int|string>>
     */
    public function setInventory(Facility $facility, array $entries, int $adminId, string $reason): array
    {
        $bloodTypeIds = $this->bloodTypes()->pluck('blood_type_id')->map(static fn ($id): int => (int) $id)->all();
        $submittedIds = array_map(static fn (array $entry): int => (int) $entry['blood_type_id'], $entries);

        if (array_diff($submittedIds, $bloodTypeIds) !== []) {
            throw ValidationException::withMessages(['inventory' => 'One or more blood types are invalid.']);
        }

        return DB::transaction(function () use ($facility, $entries, $adminId, $reason): array {
            $lockedFacility = Facility::query()
                ->where('facility_id', $facility->facility_id)
                ->lockForUpdate()
                ->firstOrFail();
            $changes = [];

            foreach ($entries as $entry) {
                $bloodTypeId = (int) $entry['blood_type_id'];
                $newUnits = max(0, (int) $entry['available_units']);

                $inventory = FacilityBloodInventory::query()
                    ->where('facility_id', $lockedFacility->facility_id)
                    ->where('blood_type_id', $bloodTypeId)
                    ->lockForUpdate()
                    ->first();

                $previousUnits = max(0, (int) ($inventory?->available_units ?? 0));
                $previousThreshold = max(0, (int) ($inventory?->low_stock_threshold ?? $this->defaultThreshold()));
                $newThreshold = array_key_exists('low_stock_threshold', $entry) && $entry['low_stock_threshold'] !== null
                    ? max(0, (int) $entry['low_stock_threshold'])
                    : $previousThreshold;

                if ($inventory && $previousUnits === $newUnits && $previousThreshold === $newThreshold) {
                    continue;
                }

                $inventory ??= new FacilityBloodInventory([
                    'facility_id' => $lockedFacility->facility_id,
                    'blood_type_id' => $bloodTypeId,
                    'reserved_units' => 0,
                ]);
                $inventory->available_units = $newUnits;
                $inventory->low_stock_threshold = $newThreshold;
                $inventory->last_updated = now();
                $inventory->updated_by_admin_id = $adminId;
                $inventory->save();

                FacilityBloodInventoryLog::query()->create([
                    'facility_id' => $lockedFacility->facility_id,
                    'blood_type_id' => $bloodTypeId,
                    'previous_units' => $previousUnits,
                    'new_units' => $newUnits,
                    'change_amount' => $newUnits - $previousUnits,
                    'action_type' => 'set',
                    'reason' => $reason,
                    'updated_by_admin_id' => $adminId,
                    'created_at' => now(),
                ]);

                $changes[] = [
                    'blood_type_id' => $bloodTypeId,
                    'previous_units' => $previousUnits,
                    'new_units' => $newUnits,
                    'change_amount' => $newUnits - $previousUnits,
                    'previous_threshold' => $previousThreshold,
                    'new_threshold' => $newThreshold,
                ];
            }

            return $changes;
        });
    }

    /**
     * Add a completed donation to facility inventory exactly once.
     *
     * The donation record is the idempotency key: the unique nullable log
     * reference is the durable guard, while the caller's transaction makes
     * the inventory update, history row, and receipt marker atomic.
     */
    public function receiveDonation(
        int $facilityId,
        int $bloodTypeId,
        int $units,
        int $adminId,
        int $donationId
    ): bool {
        if ($facilityId <= 0 || $bloodTypeId <= 0 || $units <= 0 || $donationId <= 0) {
            throw ValidationException::withMessages(['inventory' => 'Facility, verified blood type, units, and donation are required.']);
        }

        return DB::transaction(function () use ($facilityId, $bloodTypeId, $units, $adminId, $donationId): bool {
            $facility = Facility::query()
                ->where('facility_id', $facilityId)
                ->lockForUpdate()
                ->first();

            if (! $facility) {
                throw ValidationException::withMessages(['inventory' => 'The event facility no longer exists.']);
            }

            $alreadyReceived = FacilityBloodInventoryLog::query()
                ->where('related_donation_id', $donationId)
                ->exists();

            if ($alreadyReceived) {
                return false;
            }

            $inventory = FacilityBloodInventory::query()
                ->where('facility_id', $facilityId)
                ->where('blood_type_id', $bloodTypeId)
                ->lockForUpdate()
                ->first();
            $previousUnits = max(0, (int) ($inventory?->available_units ?? 0));
            $newUnits = $previousUnits + $units;

            $inventory ??= new FacilityBloodInventory([
                'facility_id' => $facilityId,
                'blood_type_id' => $bloodTypeId,
                'reserved_units' => 0,
                'low_stock_threshold' => $this->defaultThreshold(),
            ]);
            $inventory->available_units = $newUnits;
            $inventory->last_updated = now();
            $inventory->updated_by_admin_id = $adminId > 0 ? $adminId : null;
            $inventory->save();

            FacilityBloodInventoryLog::query()->create([
                'facility_id' => $facilityId,
                'blood_type_id' => $bloodTypeId,
                'previous_units' => $previousUnits,
                'new_units' => $newUnits,
                'change_amount' => $units,
                'action_type' => 'donation_received',
                'reason' => 'Completed donation DR'.str_pad((string) $donationId, 3, '0', STR_PAD_LEFT).' received into facility inventory.',
                'updated_by_admin_id' => $adminId > 0 ? $adminId : null,
                'related_donation_id' => $donationId,
                'created_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * @param array{blood_type?: string|null, facility_type?: string|null, search?: string|null} $filters
     * @return array<string, mixed>
     */
    public function getMapData(array $filters = []): array
    {
        $query = Facility::query()->whereRaw("LOWER(TRIM(COALESCE(status, 'active'))) = 'active'");
        $facilityType = $this->normalizeFilter($filters['facility_type'] ?? null);
        $search = $this->normalizeFilter($filters['search'] ?? null);
        $selectedBloodType = $this->normalizeFilter($filters['blood_type'] ?? null);

        if ($facilityType !== null) {
            $query->where('facility_type', $facilityType);
        }

        if ($search !== null) {
            $like = '%' . $search . '%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('facility_name', 'like', $like)
                    ->orWhere('barangay_name', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('province', 'like', $like);
            });
        }

        $facilities = $query->orderBy('facility_name')->get();
        $bloodTypes = $this->bloodTypes();
        $bloodTypeNames = $bloodTypes->pluck('blood_type')->all();
        $inventoryRows = $facilities->isEmpty()
            ? collect()
            : FacilityBloodInventory::query()
                ->whereIn('facility_id', $facilities->pluck('facility_id'))
                ->with('bloodType')
                ->get()
                ->groupBy('facility_id');

        $facilityData = $facilities->map(function (Facility $facility) use ($inventoryRows, $bloodTypes): array {
            $rows = collect($inventoryRows->get($facility->facility_id, collect()))->keyBy('blood_type_id');
            $types = [];
            $lastUpdated = null;

            foreach ($bloodTypes as $bloodType) {
                /** @var FacilityBloodInventory|null $row */
                $row = $rows->get($bloodType->blood_type_id);
                $units = max(0, (int) ($row?->available_units ?? 0));
                $threshold = max(0, (int) ($row?->low_stock_threshold ?? $this->defaultThreshold()));
                $types[$bloodType->blood_type] = [
                    'units' => $units,
                    'threshold' => $threshold,
                    'status' => $this->inventoryStatus($units, $threshold),
                ];

                if ($row?->last_updated && ($lastUpdated === null || $row->last_updated->gt($lastUpdated))) {
                    $lastUpdated = $row->last_updated;
                }
            }

            return [
                'facility_id' => (int) $facility->facility_id,
                'facility_name' => (string) $facility->facility_name,
                'facility_type' => (string) $facility->facility_type,
                'address' => (string) ($facility->address ?? ''),
                'barangay_name' => (string) ($facility->barangay_name ?? ''),
                'city' => (string) $facility->city,
                'province' => (string) $facility->province,
                'latitude' => $facility->latitude,
                'longitude' => $facility->longitude,
                'mapped' => $this->validCoordinate($facility->latitude, $facility->longitude),
                'blood_types' => $types,
                'last_updated' => $lastUpdated?->toIso8601String(),
            ];
        })->all();

        $summaryTypes = $selectedBloodType !== null ? [$selectedBloodType] : $bloodTypeNames;
        $totalUnits = 0;
        $facilitiesWithAvailable = 0;
        $facilitiesWithLow = 0;
        $facilitiesWithOut = 0;

        foreach ($facilityData as $facility) {
            $statuses = [];
            $facilityUnits = 0;
            foreach ($summaryTypes as $bloodType) {
                $entry = $facility['blood_types'][$bloodType] ?? ['units' => 0, 'status' => 'out_of_stock'];
                $facilityUnits += (int) $entry['units'];
                $statuses[] = $entry['status'];
            }
            $totalUnits += $facilityUnits;
            $facilitiesWithAvailable += $facilityUnits > 0 ? 1 : 0;
            $facilitiesWithLow += in_array('low', $statuses, true) ? 1 : 0;
            $facilitiesWithOut += in_array('out_of_stock', $statuses, true) ? 1 : 0;
        }

        return [
            'summary' => [
                'facilities' => count($facilityData),
                'mapped_facilities' => count(array_filter($facilityData, static fn (array $row): bool => $row['mapped'])),
                'total_units' => $totalUnits,
                'facilities_with_available' => $facilitiesWithAvailable,
                'facilities_with_low_stock' => $facilitiesWithLow,
                'facilities_with_out_of_stock' => $facilitiesWithOut,
            ],
            'blood_types' => $bloodTypeNames,
            'facilities' => array_values($facilityData),
            'map_points' => array_values(array_filter($facilityData, static fn (array $row): bool => $row['mapped'])),
            'filters' => [
                'blood_type' => $selectedBloodType,
                'facility_type' => $facilityType,
                'search' => $search,
            ],
            'last_updated' => now()->toIso8601String(),
            'freshness_notice' => 'Inventory data is based on the latest recorded facility update.',
        ];
    }

    /** @return array<string, mixed> */
    public function facilityData(Facility $facility): array
    {
        return [
            'facility_id' => (int) $facility->facility_id,
            'facility_name' => (string) $facility->facility_name,
            'facility_type' => (string) $facility->facility_type,
            'address' => $facility->address,
            'barangay_name' => $facility->barangay_name,
            'city' => (string) $facility->city,
            'province' => (string) $facility->province,
            'latitude' => $facility->latitude,
            'longitude' => $facility->longitude,
            'contact_number' => $facility->contact_number,
            'status' => (string) ($facility->status ?? 'active'),
            'mapped' => $this->validCoordinate($facility->latitude, $facility->longitude),
            'created_at' => $facility->created_at?->toIso8601String(),
            'updated_at' => $facility->updated_at?->toIso8601String(),
        ];
    }

    private function normalizeFilter(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function validCoordinate(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude)
            && is_numeric($longitude)
            && (float) $latitude >= -90
            && (float) $latitude <= 90
            && (float) $longitude >= -180
            && (float) $longitude <= 180
            && !((float) $latitude === 0.0 && (float) $longitude === 0.0);
    }
}
