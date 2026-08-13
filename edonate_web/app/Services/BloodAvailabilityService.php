<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BloodAvailabilityService
{
    /** @var array<int, string> */
    private const SUPPORTED_BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /**
     * Return the supported blood type names that actually exist in the lookup table.
     * Database IDs are intentionally never hard-coded here.
     *
     * @return array<int, string>
     */
    public function bloodTypeNames(): array
    {
        $available = DB::table('blood_types')
            ->whereIn('blood_type', self::SUPPORTED_BLOOD_TYPES)
            ->pluck('blood_type')
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        usort($available, static function (string $left, string $right): int {
            return array_search($left, self::SUPPORTED_BLOOD_TYPES, true)
                <=> array_search($right, self::SUPPORTED_BLOOD_TYPES, true);
        });

        return $available;
    }

    /**
     * Build the complete aggregate payload used by the admin page and JSON endpoint.
     *
     * @param array{blood_type?: string|null, barangay?: string|null, city?: string|null} $filters
     * @return array<string, mixed>
     */
    public function getMapData(array $filters = []): array
    {
        $bloodTypes = $this->bloodTypeNames();
        $availableRows = $this->aggregateRows(
            $this->availableDonorsQuery(),
            $filters
        );
        $scheduledRows = $this->aggregateRows(
            $this->scheduledDonorsQuery(),
            $filters
        );

        $available = $this->mergeAggregateRows($availableRows, $bloodTypes, 'available_donors');
        $scheduled = $this->mergeAggregateRows($scheduledRows, $bloodTypes, 'scheduled_donors');
        $barangays = $this->mergeBarangayAggregates($available, $scheduled, $bloodTypes);

        $bloodTypeTotals = array_fill_keys($bloodTypes, 0);
        foreach ($barangays as $barangay) {
            foreach ($bloodTypes as $bloodType) {
                $bloodTypeTotals[$bloodType] += (int) ($barangay['blood_types'][$bloodType] ?? 0);
            }
        }

        $availableDonors = array_sum($bloodTypeTotals);
        $mapPoints = array_values(array_filter($barangays, fn (array $barangay): bool =>
            $this->validCoordinate($barangay['latitude'], $barangay['longitude'])
        ));

        $mappedDonors = 0;
        foreach ($mapPoints as $point) {
            $mappedDonors += (int) $point['available_donors'];
        }

        $nonMappedDonors = max(0, $availableDonors - $mappedDonors);

        return [
            'summary' => [
                'available_donors' => $availableDonors,
                'barangays' => count(array_filter($barangays, static fn (array $row): bool => (int) $row['available_donors'] > 0)),
                'most_available_blood_type' => $this->extremeBloodType($bloodTypeTotals, true),
                'lowest_available_blood_type' => $this->extremeBloodType($bloodTypeTotals, false),
                'scheduled_donors' => array_sum(array_map(static fn (array $row): int => (int) $row['scheduled_donors'], $barangays)),
            ],
            'blood_types' => $bloodTypeTotals,
            'barangays' => array_values($barangays),
            'map_points' => array_values($mapPoints),
            'data_quality' => [
                'mapped_available_donors' => $mappedDonors,
                'available_donors_missing_coordinates' => $nonMappedDonors,
                'barangays_without_coordinates' => count(array_filter(
                    $barangays,
                    fn (array $row): bool => !$this->validCoordinate($row['latitude'], $row['longitude'])
                )),
            ],
            'filters' => [
                'blood_type' => $this->normalizeFilter($filters['blood_type'] ?? null),
                'barangay' => $this->normalizeFilter($filters['barangay'] ?? null),
                'city' => $this->normalizeFilter($filters['city'] ?? null),
            ],
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * One authoritative donor qualification query shared by all aggregates.
     */
    public function buildAvailabilityQuery(): Builder
    {
        return $this->availableDonorsQuery();
    }

    private function availableDonorsQuery(): Builder
    {
        return $this->qualifiedDonorsQuery()
            ->whereNotExists(fn (Builder $query): Builder => $this->upcomingAppointmentSubquery($query));
    }

    private function scheduledDonorsQuery(): Builder
    {
        return $this->qualifiedDonorsQuery()
            ->whereExists(fn (Builder $query): Builder => $this->upcomingAppointmentSubquery($query));
    }

    private function qualifiedDonorsQuery(): Builder
    {
        $latestEligibility = DB::table('eligibility_status')
            ->select('donor_id', DB::raw('MAX(eligibility_id) AS latest_eligibility_id'))
            ->whereNotNull('donor_id')
            ->groupBy('donor_id');

        $query = DB::table('donors AS d')
            ->join('blood_types AS bt', 'bt.blood_type_id', '=', 'd.blood_type_id')
            ->joinSub($latestEligibility, 'es_latest', function ($join): void {
                $join->on('es_latest.donor_id', '=', 'd.donor_id');
            })
            ->join('eligibility_status AS es', 'es.eligibility_id', '=', 'es_latest.latest_eligibility_id')
            ->leftJoin('locations AS l', 'l.location_id', '=', 'd.location_id')
            ->whereRaw("LOWER(TRIM(COALESCE(d.verification_status, ''))) = ?", ['verified'])
            ->whereRaw("LOWER(TRIM(COALESCE(d.blood_type_status, ''))) = ?", ['verified'])
            ->whereNotNull('d.blood_type_id')
            ->whereRaw("LOWER(TRIM(COALESCE(es.status, ''))) = ?", ['eligible'])
            ->where(function (Builder $query): void {
                $query->whereNull('es.next_eligible_date')
                    ->orWhereDate('es.next_eligible_date', '<=', Carbon::today()->toDateString());
            })
            ->whereNotNull('l.barangay_name')
            ->whereRaw("TRIM(COALESCE(l.barangay_name, '')) <> ''");

        if (Schema::hasColumn('donors', 'is_active')) {
            $query->where('d.is_active', true);
        }

        return $query;
    }

    private function upcomingAppointmentSubquery(Builder $query): Builder
    {
        return $query
            ->select(DB::raw('1'))
            ->from('appointments AS ap')
            ->whereColumn('ap.donor_id', 'd.donor_id')
            ->whereIn(DB::raw("LOWER(TRIM(COALESCE(ap.status, '')))"), ['confirmed', 'checked_in'])
            ->whereDate('ap.appointment_date', '>=', Carbon::today()->toDateString());
    }

    /**
     * @param array{blood_type?: string|null, barangay?: string|null, city?: string|null} $filters
     * @return Collection<int, object>
     */
    private function aggregateRows(Builder $query, array $filters): Collection
    {
        $query = clone $query;
        $bloodType = $this->normalizeFilter($filters['blood_type'] ?? null);
        $barangay = $this->normalizeFilter($filters['barangay'] ?? null);
        $city = $this->normalizeFilter($filters['city'] ?? null);

        if ($bloodType !== null) {
            $query->where('bt.blood_type', $bloodType);
        }

        if ($barangay !== null) {
            $query->whereRaw("LOWER(TRIM(COALESCE(l.barangay_name, ''))) LIKE ?", ['%' . strtolower($barangay) . '%']);
        }

        if ($city !== null) {
            $query->whereRaw("LOWER(TRIM(COALESCE(l.city, ''))) LIKE ?", ['%' . strtolower($city) . '%']);
        }

        return $query
            ->select(
                'l.barangay_code',
                'l.barangay_name',
                'l.city',
                'l.province',
                DB::raw('AVG(l.latitude) AS representative_latitude'),
                DB::raw('AVG(l.longitude) AS representative_longitude'),
                'bt.blood_type',
                DB::raw('COUNT(DISTINCT d.donor_id) AS donor_count')
            )
            ->groupBy('l.barangay_code', 'l.barangay_name', 'l.city', 'l.province', 'bt.blood_type')
            ->get();
    }

    /**
     * Merge case/spacing variants into one barangay aggregate without exposing donor data.
     *
     * @param Collection<int, object> $rows
     * @param array<int, string> $bloodTypes
     * @return array<string, array<string, mixed>>
     */
    private function mergeAggregateRows(Collection $rows, array $bloodTypes, string $countKey): array
    {
        $aggregates = [];

        foreach ($rows as $row) {
            $key = $this->barangayKey($row->barangay_code, $row->barangay_name, $row->city, $row->province);
            $count = (int) $row->donor_count;

            if (!isset($aggregates[$key])) {
                $aggregates[$key] = [
                    'barangay_code' => $this->nullableTrim($row->barangay_code),
                    'barangay_name' => $this->displayValue($row->barangay_name),
                    'city' => $this->displayValue($row->city),
                    'province' => $this->displayValue($row->province),
                    'latitude' => null,
                    'longitude' => null,
                    $countKey => 0,
                    'blood_types' => array_fill_keys($bloodTypes, 0),
                ];
            }

            $aggregates[$key][$countKey] += $count;
            $bloodType = $this->displayValue($row->blood_type);
            if (array_key_exists($bloodType, $aggregates[$key]['blood_types'])) {
                $aggregates[$key]['blood_types'][$bloodType] += $count;
            }

            $latitude = $this->nullableFloat($row->representative_latitude);
            $longitude = $this->nullableFloat($row->representative_longitude);
            if ($this->validCoordinate($latitude, $longitude)
                && !$this->validCoordinate($aggregates[$key]['latitude'], $aggregates[$key]['longitude'])) {
                $aggregates[$key]['latitude'] = $latitude;
                $aggregates[$key]['longitude'] = $longitude;
            }
        }

        return $aggregates;
    }

    /**
     * @param array<string, array<string, mixed>> $available
     * @param array<string, array<string, mixed>> $scheduled
     * @param array<int, string> $bloodTypes
     * @return array<string, array<string, mixed>>
     */
    private function mergeBarangayAggregates(array $available, array $scheduled, array $bloodTypes): array
    {
        $merged = $available;

        foreach ($scheduled as $key => $row) {
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'barangay_code' => $row['barangay_code'],
                    'barangay_name' => $row['barangay_name'],
                    'city' => $row['city'],
                    'province' => $row['province'],
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'available_donors' => 0,
                    'scheduled_donors' => 0,
                    'blood_types' => array_fill_keys($bloodTypes, 0),
                ];
            }

            $merged[$key]['scheduled_donors'] = (int) ($row['scheduled_donors'] ?? 0);
            if (!$this->validCoordinate($merged[$key]['latitude'], $merged[$key]['longitude'])) {
                $merged[$key]['latitude'] = $row['latitude'];
                $merged[$key]['longitude'] = $row['longitude'];
            }
        }

        foreach ($merged as &$row) {
            $row['scheduled_donors'] = (int) ($row['scheduled_donors'] ?? 0);
            $row['available_donors'] = (int) ($row['available_donors'] ?? 0);
            $row['availability_level'] = $this->availabilityLevel($row['available_donors']);
            $row['mapped'] = $this->validCoordinate($row['latitude'], $row['longitude']);
        }
        unset($row);

        uasort($merged, static function (array $left, array $right): int {
            return [$left['city'], $left['barangay_name']] <=> [$right['city'], $right['barangay_name']];
        });

        return $merged;
    }

    /** @param array<string, int> $totals */
    private function extremeBloodType(array $totals, bool $highest): ?array
    {
        if ($totals === [] || max($totals) === 0) {
            return null;
        }

        $value = $highest ? max($totals) : min(array_filter($totals, static fn (int $count): bool => $count > 0));
        $type = array_key_first(array_filter($totals, static fn (int $count): bool => $count === $value));

        return $type === null ? null : ['blood_type' => $type, 'count' => $value];
    }

    private function availabilityLevel(int $count): string
    {
        return match (true) {
            $count >= 10 => 'high',
            $count >= 5 => 'moderate',
            $count > 0 => 'low',
            default => 'none',
        };
    }

    private function barangayKey(mixed $code, mixed $name, mixed $city, mixed $province): string
    {
        $code = $this->nullableTrim($code);
        if ($code !== null) {
            return 'code:' . strtolower($code);
        }

        return 'fallback:' . implode('|', array_map(
            static fn (mixed $value): string => strtolower(trim((string) ($value ?? ''))),
            [$name, $city, $province]
        ));
    }

    private function normalizeFilter(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = $this->normalizeFilter($value);

        return $value === '' ? null : $value;
    }

    private function displayValue(mixed $value): string
    {
        return $this->nullableTrim($value) ?? 'Unknown';
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
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
