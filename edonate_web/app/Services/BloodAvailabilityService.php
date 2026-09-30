<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BloodAvailabilityService
{
    /** @var array<int, string> */
    private const SUPPORTED_BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /**
     * Return the supported blood groups used by the map filters and breakdowns.
     *
     * @return array<int, string>
     */
    public function bloodTypeNames(): array
    {
        return self::SUPPORTED_BLOOD_TYPES;
    }

    /**
     * Build one aggregate payload from distinct donors with completed donation
     * history. The summary, table rows, markers and unmapped list all originate
     * from this same filtered donor set.
     *
     * @param array{blood_type?: string|null, barangay?: string|null, city?: string|null} $filters
     * @return array<string, mixed>
     */
    public function getMapData(array $filters = []): array
    {
        $filters = [
            'blood_type' => $this->normalizeFilter($filters['blood_type'] ?? null),
            'barangay' => $this->normalizeFilter($filters['barangay'] ?? null),
            'city' => $this->normalizeFilter($filters['city'] ?? null),
        ];
        $bloodCategories = [...$this->bloodTypeNames(), 'Unknown'];
        $donors = $this->applyFilters($this->buildAvailabilityQuery(), $filters)
            ->orderBy('d.donor_id')
            ->get();

        $seenDonors = [];
        $bloodTypeTotals = array_fill_keys($bloodCategories, 0);
        $confidenceTotals = ['confirmed' => 0, 'unconfirmed' => 0, 'unknown' => 0];
        $bloodTypeConfidenceTotals = $this->emptyBloodTypeConfidence($bloodCategories);
        $barangays = [];
        $unmappedDonors = [];
        $scheduledDonors = 0;

        foreach ($donors as $donor) {
            $donorId = (int) $donor->donor_id;
            if (isset($seenDonors[$donorId])) {
                continue;
            }
            $seenDonors[$donorId] = true;

            $bloodType = strtoupper(trim((string) ($donor->resolved_blood_type ?? 'Unknown')));
            if (! array_key_exists($bloodType, $bloodTypeTotals)) {
                $bloodType = 'Unknown';
            }
            $confidence = in_array($donor->blood_type_confidence, ['confirmed', 'unconfirmed'], true)
                ? $donor->blood_type_confidence
                : 'unknown';

            $bloodTypeTotals[$bloodType]++;
            $confidenceTotals[$confidence]++;
            $bloodTypeConfidenceTotals[$bloodType][$confidence]++;
            $hasUpcomingAppointment = (bool) $donor->has_upcoming_appointment;
            if ($hasUpcomingAppointment) {
                $scheduledDonors++;
            }

            $barangayName = $this->nullableTrim($donor->barangay_name);
            $city = $this->nullableTrim($donor->city);
            $province = $this->nullableTrim($donor->province);
            $missingLocationFields = [];

            if (empty($donor->registered_location_id)) {
                $missingLocationFields[] = 'Registered location is not set.';
            } elseif (empty($donor->matched_location_id)) {
                $missingLocationFields[] = 'Registered location record was not found.';
            }

            if ($barangayName === null) {
                $missingLocationFields[] = 'Barangay name is missing.';
            }

            if ($missingLocationFields !== []) {
                $unmappedDonors[] = $this->unmappedDonorPayload(
                    $donorId,
                    $barangayName,
                    $city,
                    $bloodType,
                    $confidence,
                    $missingLocationFields
                );
            }

            if ($barangayName === null) {
                continue;
            }

            $key = $this->barangayKey(
                $donor->barangay_code,
                $barangayName,
                $city,
                $province
            );
            if (! isset($barangays[$key])) {
                $barangays[$key] = [
                    'barangay_code' => $this->nullableTrim($donor->barangay_code),
                    'barangay_name' => $barangayName,
                    'city' => $city ?? 'Unknown',
                    'province' => $province ?? 'Unknown',
                    'completed_donors' => 0,
                    'scheduled_donors' => 0,
                    'blood_types' => array_fill_keys($bloodCategories, 0),
                    'blood_type_confidence' => ['confirmed' => 0, 'unconfirmed' => 0, 'unknown' => 0],
                    'blood_type_confidence_by_type' => $this->emptyBloodTypeConfidence($bloodCategories),
                    '_coordinate_samples' => [],
                    '_donors_for_unmapped' => [],
                ];
            }

            $barangays[$key]['completed_donors']++;
            if ($hasUpcomingAppointment) {
                $barangays[$key]['scheduled_donors']++;
            }
            $barangays[$key]['blood_types'][$bloodType]++;
            $barangays[$key]['blood_type_confidence'][$confidence]++;
            $barangays[$key]['blood_type_confidence_by_type'][$bloodType][$confidence]++;
            $barangays[$key]['_donors_for_unmapped'][] = [
                'donor_id' => $donorId,
                'blood_type' => $bloodType,
                'blood_type_confidence' => $confidence,
            ];

            $latitude = $this->nullableFloat($donor->latitude);
            $longitude = $this->nullableFloat($donor->longitude);
            if ($this->validCoordinate($latitude, $longitude)) {
                $barangays[$key]['_coordinate_samples'][] = [$latitude, $longitude];
            }
        }

        foreach ($barangays as $key => &$barangay) {
            $reference = $this->barangayMapReferenceCoordinates(
                $barangay['barangay_name'],
                $barangay['city'],
                $barangay['province']
            );
            $samples = $barangay['_coordinate_samples'];

            if ($reference !== null) {
                $latitude = $reference['latitude'];
                $longitude = $reference['longitude'];
            } elseif ($samples !== []) {
                $latitude = array_sum(array_column($samples, 0)) / count($samples);
                $longitude = array_sum(array_column($samples, 1)) / count($samples);
            } else {
                $latitude = null;
                $longitude = null;
            }

            $barangay['latitude'] = $this->validCoordinate($latitude, $longitude) ? round($latitude, 2) : null;
            $barangay['longitude'] = $this->validCoordinate($latitude, $longitude) ? round($longitude, 2) : null;
            $barangay['verified_donors'] = $barangay['completed_donors'];
            $barangay['available_donors'] = $barangay['completed_donors'];
            $barangay['unconfirmed_donors'] = $barangay['blood_type_confidence']['unconfirmed'];
            $barangay['unknown_type_donors'] = $barangay['blood_type_confidence']['unknown'];
            $barangay['availability_level'] = $this->availabilityLevel($barangay['completed_donors']);
            $barangay['mapped'] = $this->validCoordinate($barangay['latitude'], $barangay['longitude']);

            if (! $barangay['mapped']) {
                foreach ($barangay['_donors_for_unmapped'] as $unmapped) {
                    $unmappedDonors[] = $this->unmappedDonorPayload(
                        $unmapped['donor_id'],
                        $barangay['barangay_name'],
                        $barangay['city'],
                        $unmapped['blood_type'],
                        $unmapped['blood_type_confidence'],
                        ['No usable barangay coordinates or maintained barangay center are available.']
                    );
                }
            }

            unset($barangay['_coordinate_samples'], $barangay['_donors_for_unmapped']);
        }
        unset($barangay);

        uasort($barangays, static fn (array $left, array $right): int =>
            [$left['city'], $left['barangay_name']] <=> [$right['city'], $right['barangay_name']]
        );

        $barangays = array_values($barangays);
        $mapPoints = array_values(array_filter(
            $barangays,
            static fn (array $barangay): bool => $barangay['mapped']
        ));
        $completedDonors = count($seenDonors);
        $mappedCompletedDonors = array_sum(array_column($mapPoints, 'completed_donors'));
        $missingCoordinates = count(array_filter(
            $unmappedDonors,
            static fn (array $donor): bool => in_array(
                'No usable barangay coordinates or maintained barangay center are available.',
                $donor['missing_fields'],
                true
            )
        ));
        $missingBarangay = count(array_filter(
            $unmappedDonors,
            static fn (array $donor): bool => in_array('Barangay name is missing.', $donor['missing_fields'], true)
                || in_array('Registered location is not set.', $donor['missing_fields'], true)
                || in_array('Registered location record was not found.', $donor['missing_fields'], true)
        ));
        $mostCommonType = $this->extremeBloodType($bloodTypeTotals, true);
        $leastCommonType = $this->extremeBloodType($bloodTypeTotals, false);
        $barangaysWithDonors = count($barangays);

        return [
            'summary' => [
                'completed_donors' => $completedDonors,
                'mapped_completed_donors' => $mappedCompletedDonors,
                'verified_donors' => $completedDonors,
                'available_donors' => $completedDonors,
                'barangays' => $barangaysWithDonors,
                'scheduled_donors' => $scheduledDonors,
                'confirmed_donors' => $confidenceTotals['confirmed'],
                'unconfirmed_donors' => $confidenceTotals['unconfirmed'],
                'unknown_type_donors' => $confidenceTotals['unknown'],
                'blood_type_confidence_by_type' => $bloodTypeConfidenceTotals,
                'most_common_blood_type' => $mostCommonType,
                'least_common_blood_type' => $leastCommonType,
                // Backward-compatible keys; both use the completed-donor set.
                'most_available_blood_type' => $mostCommonType,
                'lowest_available_blood_type' => $leastCommonType,
            ],
            'blood_types' => $bloodTypeTotals,
            'blood_type_confidence' => $confidenceTotals,
            'blood_type_confidence_by_type' => $bloodTypeConfidenceTotals,
            'barangays' => $barangays,
            'map_points' => $mapPoints,
            'data_quality' => [
                'mapped_completed_donors' => $mappedCompletedDonors,
                'completed_donors_missing_coordinates' => $missingCoordinates,
                'completed_donors_missing_barangay' => $missingBarangay,
                'unmapped_completed_donor_count' => count($unmappedDonors),
                'unmapped_completed_donors' => array_values($unmappedDonors),
                'barangays_without_coordinates' => count(array_filter(
                    $barangays,
                    static fn (array $row): bool => ! $row['mapped']
                )),
                // Legacy aliases retained for existing admin clients.
                'mapped_verified_donors' => $mappedCompletedDonors,
                'verified_donors_missing_coordinates' => $missingCoordinates,
                'mapped_available_donors' => $mappedCompletedDonors,
                'available_donors_missing_coordinates' => $missingCoordinates,
            ],
            'filters' => $filters,
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Authoritative query: one row per donor with at least one completed
     * donation record. Eligibility, account status and blood-type verification
     * do not control inclusion in completed-donor coverage.
     */
    public function buildAvailabilityQuery(): Builder
    {
        $completedDonors = DB::table('donation_records AS completed_dr')
            ->select('completed_dr.donor_id')
            ->whereNotNull('completed_dr.donor_id')
            ->whereRaw("LOWER(TRIM(COALESCE(completed_dr.donation_status, ''))) = ?", ['completed'])
            ->groupBy('completed_dr.donor_id');

        $latestConfirmedType = DB::table('donation_records AS typed_dr')
            ->join('blood_types AS confirmed_candidate', 'confirmed_candidate.blood_type_id', '=', 'typed_dr.verified_blood_type_id')
            ->select('typed_dr.donor_id')
            ->selectRaw('MAX(typed_dr.donation_id) AS latest_confirmed_donation_id')
            ->whereNotNull('typed_dr.verified_blood_type_id')
            ->whereRaw("LOWER(TRIM(COALESCE(typed_dr.donation_status, ''))) = ?", ['completed'])
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(confirmed_candidate.blood_type, '')))"), self::SUPPORTED_BLOOD_TYPES)
            ->groupBy('typed_dr.donor_id');

        $query = DB::table('donors AS d')
            ->joinSub($completedDonors, 'completed_donors', function ($join): void {
                $join->on('completed_donors.donor_id', '=', 'd.donor_id');
            })
            ->leftJoinSub($latestConfirmedType, 'latest_confirmed_type', function ($join): void {
                $join->on('latest_confirmed_type.donor_id', '=', 'd.donor_id');
            })
            ->leftJoin('donation_records AS confirmed_dr', 'confirmed_dr.donation_id', '=', 'latest_confirmed_type.latest_confirmed_donation_id')
            ->leftJoin('blood_types AS confirmed_bt', 'confirmed_bt.blood_type_id', '=', 'confirmed_dr.verified_blood_type_id')
            ->leftJoin('blood_types AS profile_bt', 'profile_bt.blood_type_id', '=', 'd.blood_type_id')
            ->leftJoin('locations AS l', 'l.location_id', '=', 'd.location_id')
            ->select([
                'd.donor_id',
                'd.location_id AS registered_location_id',
                'l.location_id AS matched_location_id',
                'l.barangay_code',
                'l.barangay_name',
                'l.city',
                'l.province',
                'l.latitude',
                'l.longitude',
            ])
            ->selectRaw($this->resolvedBloodTypeSql().' AS resolved_blood_type')
            ->selectRaw($this->bloodTypeConfidenceSql().' AS blood_type_confidence')
            ->selectRaw(
                "CASE WHEN EXISTS (
                    SELECT 1 FROM appointments AS ap
                    WHERE ap.donor_id = d.donor_id
                      AND LOWER(TRIM(COALESCE(ap.status, ''))) IN ('confirmed', 'checked_in')
                      AND DATE(ap.appointment_date) >= ?
                ) THEN 1 ELSE 0 END AS has_upcoming_appointment",
                [Carbon::today()->toDateString()]
            );

        return $query;
    }

    /**
     * @param array{blood_type: string|null, barangay: string|null, city: string|null} $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['blood_type'] !== null) {
            $type = strtoupper($filters['blood_type']);
            $query->whereRaw($this->resolvedBloodTypeSql().' = ?', [$type === 'UNKNOWN' ? 'Unknown' : $type]);
        }

        if ($filters['barangay'] !== null) {
            $query->whereRaw(
                "LOWER(TRIM(COALESCE(l.barangay_name, ''))) LIKE ?",
                ['%'.strtolower($filters['barangay']).'%']
            );
        }

        if ($filters['city'] !== null) {
            $query->whereRaw(
                "LOWER(TRIM(COALESCE(l.city, ''))) LIKE ?",
                ['%'.strtolower($filters['city']).'%']
            );
        }

        return $query;
    }

    private function resolvedBloodTypeSql(): string
    {
        $supported = $this->supportedBloodTypeSqlList();
        $confirmed = "UPPER(TRIM(COALESCE(confirmed_bt.blood_type, '')))";
        $profile = "UPPER(TRIM(COALESCE(profile_bt.blood_type, '')))";

        return "CASE
            WHEN {$confirmed} IN ({$supported}) THEN {$confirmed}
            WHEN {$profile} IN ({$supported}) THEN {$profile}
            ELSE 'Unknown'
        END";
    }

    private function bloodTypeConfidenceSql(): string
    {
        $supported = $this->supportedBloodTypeSqlList();
        $confirmed = "UPPER(TRIM(COALESCE(confirmed_bt.blood_type, '')))";
        $profile = "UPPER(TRIM(COALESCE(profile_bt.blood_type, '')))";

        return "CASE
            WHEN {$confirmed} IN ({$supported}) THEN 'confirmed'
            WHEN {$profile} IN ({$supported})
                AND LOWER(TRIM(COALESCE(d.blood_type_status, ''))) = 'verified' THEN 'confirmed'
            WHEN {$profile} IN ({$supported}) THEN 'unconfirmed'
            ELSE 'unknown'
        END";
    }

    private function supportedBloodTypeSqlList(): string
    {
        return "'".implode("','", self::SUPPORTED_BLOOD_TYPES)."'";
    }

    /**
     * @param array<int, string> $bloodTypes
     * @return array<string, array{confirmed: int, unconfirmed: int, unknown: int}>
     */
    private function emptyBloodTypeConfidence(array $bloodTypes): array
    {
        $confidence = [];
        foreach ($bloodTypes as $bloodType) {
            $confidence[$bloodType] = ['confirmed' => 0, 'unconfirmed' => 0, 'unknown' => 0];
        }

        return $confidence;
    }

    /**
     * @param array<int, string> $missingFields
     * @return array<string, mixed>
     */
    private function unmappedDonorPayload(
        int $donorId,
        ?string $barangay,
        ?string $city,
        string $bloodType,
        string $confidence,
        array $missingFields
    ): array {
        return [
            'donor_reference' => 'D'.$donorId,
            'barangay_name' => $barangay ?? 'Not recorded',
            'city' => $city ?? 'Not recorded',
            'blood_type' => $bloodType,
            'blood_type_confidence' => $confidence,
            'missing_fields' => array_values(array_unique($missingFields)),
        ];
    }

    /**
     * Resolve maintained barangay centers when available. Otherwise coordinates
     * are averaged per barangay and rounded before leaving the server.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    private function barangayMapReferenceCoordinates(string $barangay, string $city, string $province): ?array
    {
        $barangay = strtolower(trim($barangay));
        $city = strtolower(trim($city));
        $province = strtolower(trim($province));

        foreach (config('blood_availability.barangay_map_references', []) as $reference) {
            if (! is_array($reference)) {
                continue;
            }

            $referenceCities = array_map(
                static fn (mixed $value): string => strtolower(trim((string) $value)),
                $reference['cities'] ?? []
            );

            if ($barangay !== strtolower(trim((string) ($reference['barangay'] ?? '')))
                || ! in_array($city, $referenceCities, true)
                || $province !== strtolower(trim((string) ($reference['province'] ?? '')))) {
                continue;
            }

            $latitude = $this->nullableFloat($reference['latitude'] ?? null);
            $longitude = $this->nullableFloat($reference['longitude'] ?? null);
            if ($this->validCoordinate($latitude, $longitude)) {
                return ['latitude' => $latitude, 'longitude' => $longitude];
            }
        }

        return null;
    }

    private function extremeBloodType(array $totals, bool $highest): ?array
    {
        if ($totals === [] || max($totals) === 0) {
            return null;
        }

        $nonZero = array_filter($totals, static fn (int $count): bool => $count > 0);
        $value = $highest ? max($totals) : min($nonZero);
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
            return 'code:'.strtolower($code);
        }

        return 'fallback:'.implode('|', array_map(
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
            && ! ((float) $latitude === 0.0 && (float) $longitude === 0.0);
    }
}
