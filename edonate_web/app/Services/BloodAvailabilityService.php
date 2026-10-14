<?php

namespace App\Services;

use App\Support\EligibilityStatus as EligibilityStatusValue;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * history. Barangay and city filters narrow the dataset; blood type selects
     * marker color while the summary, table and popup retain all blood groups.
     *
     * @param  array{blood_type?: string|null, barangay?: string|null, city?: string|null}  $filters
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
        $selectedType = $filters['blood_type'] === null
            ? null
            : (strtoupper($filters['blood_type']) === 'UNKNOWN' ? 'Unknown' : strtoupper($filters['blood_type']));
        $donors = $this->applyLocationFilters($this->buildAvailabilityQuery(), $filters)
            ->orderBy('d.donor_id')
            ->get();

        $seenDonors = [];
        $bloodTypeTotals = array_fill_keys($bloodCategories, 0);
        $confidenceTotals = ['confirmed' => 0, 'unconfirmed' => 0, 'unknown' => 0];
        $bloodTypeConfidenceTotals = $this->emptyBloodTypeConfidence($bloodCategories);
        $barangays = [];
        $unmappedDonorsById = [];
        $unmappedAvailabilityById = [];
        $scheduledDonors = 0;
        $availabilityTotals = ['available' => 0, 'temporarily_deferred' => 0, 'not_available' => 0];

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
            $availability = $this->donorAvailability($donor);
            $bloodTypeTotals[$bloodType]++;
            $confidenceTotals[$confidence]++;
            $bloodTypeConfidenceTotals[$bloodType][$confidence]++;
            $availabilityTotals[$availability['status']]++;

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
            if ($city === null) {
                $missingLocationFields[] = 'City or municipality is missing.';
            }
            if ($province === null) {
                $missingLocationFields[] = 'Province is missing.';
            }

            if ($missingLocationFields !== []) {
                $unmappedDonorsById[$donorId] = $this->unmappedDonorPayload(
                    $donorId,
                    $barangayName,
                    $city,
                    $bloodType,
                    $confidence,
                    $missingLocationFields,
                    is_numeric($donor->matched_location_id) ? (int) $donor->matched_location_id : null
                );
                $unmappedAvailabilityById[$donorId] = $availability['status'];
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
                    'boundary_match_name' => null,
                    'coordinate_source' => null,
                    'completed_donors' => 0,
                    'completed_history_donors' => 0,
                    'scheduled_donors' => 0,
                    'blood_types' => array_fill_keys($bloodCategories, 0),
                    'blood_type_confidence' => ['confirmed' => 0, 'unconfirmed' => 0, 'unknown' => 0],
                    'blood_type_confidence_by_type' => $this->emptyBloodTypeConfidence($bloodCategories),
                    'blood_type_availability' => $this->emptyBloodTypeAvailability($bloodCategories),
                    '_donors_for_unmapped' => [],
                ];
            }

            $barangays[$key]['completed_history_donors']++;
            $barangays[$key]['completed_donors']++;
            $barangays[$key]['blood_types'][$bloodType]++;
            $barangays[$key]['blood_type_confidence'][$confidence]++;
            $barangays[$key]['blood_type_confidence_by_type'][$bloodType][$confidence]++;
            if ($hasUpcomingAppointment) {
                $barangays[$key]['scheduled_donors']++;
            }

            $typeAvailability = &$barangays[$key]['blood_type_availability'][$bloodType];
            $typeAvailability['completed_donors']++;
            $stateCountKey = $availability['status'] === 'temporarily_deferred'
                ? 'temporarily_deferred_donors'
                : $availability['status'].'_donors';
            $typeAvailability[$stateCountKey]++;
            if ($availability['status'] === 'temporarily_deferred'
                && $availability['next_eligible_date'] !== null
                && ($typeAvailability['earliest_next_eligible_date'] === null
                    || $availability['next_eligible_date'] < $typeAvailability['earliest_next_eligible_date'])) {
                $typeAvailability['earliest_next_eligible_date'] = $availability['next_eligible_date'];
            }
            unset($typeAvailability);

            $barangays[$key]['_donors_for_unmapped'][] = [
                'donor_id' => $donorId,
                'location_id' => is_numeric($donor->matched_location_id) ? (int) $donor->matched_location_id : null,
                'blood_type' => $bloodType,
                'blood_type_confidence' => $confidence,
                'availability_status' => $availability['status'],
            ];
        }

        $centerLookup = app(PhilippinesBarangayCenterIndex::class);
        foreach ($barangays as $key => &$barangay) {
            $center = $centerLookup->findCenter(
                $barangay['barangay_code'],
                $barangay['barangay_name'],
                $barangay['city'],
                $barangay['province']
            );
            $reference = $center ?? $this->barangayMapReferenceCoordinates(
                $barangay['barangay_name'],
                $barangay['city'],
                $barangay['province']
            );

            $latitude = $reference['latitude'] ?? null;
            $longitude = $reference['longitude'] ?? null;
            $barangay['boundary_match_name'] = $center['matched_barangay'] ?? null;
            $barangay['coordinate_source'] = $center !== null ? 'faeldon_barangay_boundary_2019' : ($reference !== null ? 'maintained_center' : null);
            $barangay['latitude'] = $this->validCoordinate($latitude, $longitude) ? round($latitude, 5) : null;
            $barangay['longitude'] = $this->validCoordinate($latitude, $longitude) ? round($longitude, 5) : null;
            foreach ($barangay['blood_type_availability'] as &$typeAvailability) {
                $typeAvailability['status'] = $this->aggregateAvailabilityStatus($typeAvailability);
                $typeAvailability['counts'] = [
                    'available' => $typeAvailability['available_donors'],
                    'temporarily_deferred' => $typeAvailability['temporarily_deferred_donors'],
                    'not_available' => $typeAvailability['not_available_donors'],
                ];
            }
            unset($typeAvailability);

            $barangay['verified_donors'] = $barangay['completed_donors'];
            $barangay['available_donors'] = array_sum(array_column($barangay['blood_type_availability'], 'available_donors'));
            $barangay['unconfirmed_donors'] = $barangay['blood_type_confidence']['unconfirmed'];
            $barangay['unknown_type_donors'] = $barangay['blood_type_confidence']['unknown'];
            $barangay['availability_level'] = $selectedType !== null
                ? ($barangay['blood_type_availability'][$selectedType]['status'] ?? 'not_available')
                : $this->bestAvailabilityStatus($barangay['blood_type_availability']);
            $barangay['mapped'] = $this->validCoordinate($barangay['latitude'], $barangay['longitude']);

            if (! $barangay['mapped']) {
                foreach ($barangay['_donors_for_unmapped'] as $unmapped) {
                    $payload = $this->unmappedDonorPayload(
                        $unmapped['donor_id'],
                        $barangay['barangay_name'],
                        $barangay['city'],
                        $unmapped['blood_type'],
                        $unmapped['blood_type_confidence'],
                        ['No matching barangay boundary or maintained center was found for this barangay, city, and province.'],
                        $unmapped['location_id']
                    );
                    $existing = $unmappedDonorsById[$unmapped['donor_id']] ?? null;
                    if ($existing !== null) {
                        $payload['missing_fields'] = array_values(array_unique([
                            ...$existing['missing_fields'],
                            ...$payload['missing_fields'],
                        ]));
                    }
                    $unmappedDonorsById[$unmapped['donor_id']] = $payload;
                    $unmappedAvailabilityById[$unmapped['donor_id']] = $unmapped['availability_status'];
                }
            }

            unset($barangay['_donors_for_unmapped']);
        }
        unset($barangay);

        uasort($barangays, static function (array $left, array $right): int {
            return [$left['city'], $left['barangay_name']] <=> [$right['city'], $right['barangay_name']];
        });

        $barangays = array_values($barangays);
        $mapPoints = array_values(array_filter(
            $barangays,
            static fn (array $barangay): bool => $barangay['mapped']
        ));
        $unmappedDonors = array_values($unmappedDonorsById);
        $completedDonors = count($seenDonors);
        $mappedCompletedDonors = array_sum(array_column($mapPoints, 'completed_donors'));
        $mappedAvailableDonors = 0;
        $mappedDeferredDonors = 0;
        foreach ($mapPoints as $point) {
            foreach ($bloodCategories as $type) {
                $mappedAvailableDonors += (int) ($point['blood_type_availability'][$type]['available_donors'] ?? 0);
                $mappedDeferredDonors += (int) ($point['blood_type_availability'][$type]['temporarily_deferred_donors'] ?? 0);
            }
        }
        $missingCoordinates = count(array_filter(
            $unmappedDonors,
            static fn (array $donor): bool => in_array(
                'No matching barangay boundary or maintained center was found for this barangay, city, and province.',
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
        $unmappedAvailabilityCounts = array_count_values(array_values($unmappedAvailabilityById));
        $mostCommonType = $this->extremeBloodType($bloodTypeTotals, true);
        $leastCommonType = $this->extremeBloodType($bloodTypeTotals, false);
        $barangaysWithDonors = count($barangays);

        return [
            'summary' => [
                'completed_donors' => $completedDonors,
                'mapped_completed_donors' => $mappedCompletedDonors,
                'verified_donors' => $completedDonors,
                'available_donors' => $availabilityTotals['available'],
                'temporarily_deferred_donors' => $availabilityTotals['temporarily_deferred'],
                'not_available_donors' => $availabilityTotals['not_available'],
                'mapped_available_donors' => $mappedAvailableDonors,
                'mapped_temporarily_deferred_donors' => $mappedDeferredDonors,
                'barangays' => $barangaysWithDonors,
                'scheduled_donors' => $scheduledDonors,
                'confirmed_donors' => $confidenceTotals['confirmed'],
                'unconfirmed_donors' => $confidenceTotals['unconfirmed'],
                'unknown_type_donors' => $confidenceTotals['unknown'],
                'blood_type_confidence_by_type' => $bloodTypeConfidenceTotals,
                'most_common_blood_type' => $mostCommonType,
                'least_common_blood_type' => $leastCommonType,
                // Legacy key retained; it now reflects actual eligibility, not history alone.
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
                'mapped_available_donors' => $mappedAvailableDonors,
                'completed_donors_missing_coordinates' => $missingCoordinates,
                'completed_donors_missing_barangay' => $missingBarangay,
                'unmapped_completed_donor_count' => count($unmappedDonors),
                'unmapped_completed_donors' => array_values($unmappedDonors),
                'available_donors_missing_coordinates' => (int) ($unmappedAvailabilityCounts['available'] ?? 0),
                'temporarily_deferred_donors_missing_coordinates' => (int) ($unmappedAvailabilityCounts['temporarily_deferred'] ?? 0),
                'not_available_donors_missing_coordinates' => (int) ($unmappedAvailabilityCounts['not_available'] ?? 0),
                'barangays_without_coordinates' => count(array_filter(
                    $barangays,
                    static fn (array $row): bool => ! $row['mapped']
                )),
                // Legacy aliases retained for existing admin clients.
                'mapped_verified_donors' => $mappedCompletedDonors,
                'verified_donors_missing_coordinates' => $missingCoordinates,
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

        $hasLatestEligibility = Schema::hasTable('eligibility_status')
            && Schema::hasColumns('eligibility_status', ['eligibility_id', 'donor_id', 'status']);
        $hasLatestAuthentication = Schema::hasTable('donor_authentication')
            && Schema::hasColumns('donor_authentication', ['auth_id', 'donor_id', 'is_verified']);

        $latestEligibility = $hasLatestEligibility
            ? DB::table('eligibility_status AS eligibility_rows')
                ->select('eligibility_rows.donor_id')
                ->selectRaw('MAX(eligibility_rows.eligibility_id) AS latest_eligibility_id')
                ->groupBy('eligibility_rows.donor_id')
            : null;
        $latestAuthentication = $hasLatestAuthentication
            ? DB::table('donor_authentication AS authentication_rows')
                ->select('authentication_rows.donor_id')
                ->selectRaw('MAX(authentication_rows.auth_id) AS latest_auth_id')
                ->groupBy('authentication_rows.donor_id')
            : null;

        $hasActiveFlag = Schema::hasColumn('donors', 'is_active');
        $hasIdentityStatus = Schema::hasColumn('donors', 'verification_status');
        $hasBloodTypeStatus = Schema::hasColumn('donors', 'blood_type_status');

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
            ->when($hasLatestEligibility, function (Builder $query) use ($latestEligibility): void {
                $query->leftJoinSub($latestEligibility, 'latest_eligibility', function ($join): void {
                    $join->on('latest_eligibility.donor_id', '=', 'd.donor_id');
                })->leftJoin('eligibility_status AS current_eligibility', 'current_eligibility.eligibility_id', '=', 'latest_eligibility.latest_eligibility_id');
            })
            ->when($hasLatestAuthentication, function (Builder $query) use ($latestAuthentication): void {
                $query->leftJoinSub($latestAuthentication, 'latest_authentication', function ($join): void {
                    $join->on('latest_authentication.donor_id', '=', 'd.donor_id');
                })->leftJoin('donor_authentication AS current_authentication', 'current_authentication.auth_id', '=', 'latest_authentication.latest_auth_id');
            })
            ->select([
                'd.donor_id',
                'd.location_id AS registered_location_id',
                'l.location_id AS matched_location_id',
                'l.barangay_code',
                'l.barangay_name',
                'l.city',
                'l.province',
            ])
            ->selectRaw(($hasActiveFlag ? 'd.is_active' : '1').' AS account_is_active')
            ->selectRaw(($hasIdentityStatus ? 'd.verification_status' : "'unverified'").' AS verification_status')
            ->selectRaw(($hasBloodTypeStatus ? 'd.blood_type_status' : 'NULL').' AS profile_blood_type_status')
            ->selectRaw(($hasLatestEligibility ? 'current_eligibility.status' : 'NULL').' AS latest_eligibility_status')
            ->selectRaw(($hasLatestEligibility && Schema::hasColumn('eligibility_status', 'next_eligible_date')
                ? 'current_eligibility.next_eligible_date'
                : 'NULL').' AS next_eligible_date')
            ->selectRaw(($hasLatestAuthentication ? 'current_authentication.is_verified' : 'NULL').' AS account_is_verified')
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
     * @param  array{blood_type: string|null, barangay: string|null, city: string|null}  $filters
     */
    private function applyLocationFilters(Builder $query, array $filters): Builder
    {
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
        $profileStatus = Schema::hasColumn('donors', 'blood_type_status')
            ? "LOWER(TRIM(COALESCE(d.blood_type_status, '')))"
            : "''";

        return "CASE
            WHEN {$confirmed} IN ({$supported}) THEN 'confirmed'
            WHEN {$profile} IN ({$supported})
                AND {$profileStatus} = 'verified' THEN 'confirmed'
            WHEN {$profile} IN ({$supported}) THEN 'unconfirmed'
            ELSE 'unknown'
        END";
    }

    private function supportedBloodTypeSqlList(): string
    {
        return "'".implode("','", self::SUPPORTED_BLOOD_TYPES)."'";
    }

    /**
     * @param  array<int, string>  $bloodTypes
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
     * @param  array<int, string>  $bloodTypes
     * @return array<string, array<string, mixed>>
     */
    private function emptyBloodTypeAvailability(array $bloodTypes): array
    {
        $availability = [];
        foreach ($bloodTypes as $bloodType) {
            $availability[$bloodType] = [
                'status' => 'not_available',
                'completed_donors' => 0,
                'available_donors' => 0,
                'temporarily_deferred_donors' => 0,
                'not_available_donors' => 0,
                'earliest_next_eligible_date' => null,
                'counts' => [
                    'available' => 0,
                    'temporarily_deferred' => 0,
                    'not_available' => 0,
                ],
            ];
        }

        return $availability;
    }

    /** @return array{status: string, next_eligible_date: string|null} */
    private function donorAvailability(object $donor): array
    {
        $accountActive = (bool) ($donor->account_is_active ?? true)
            && ($donor->account_is_verified === null || (bool) $donor->account_is_verified);
        $identityVerified = strtolower(trim((string) ($donor->verification_status ?? ''))) === 'verified';

        if (! $accountActive || ! $identityVerified) {
            return ['status' => 'not_available', 'next_eligible_date' => null];
        }

        $eligibility = EligibilityStatusValue::normalize($donor->latest_eligibility_status ?? null);
        $nextEligibleDate = $this->normalizedDate($donor->next_eligible_date ?? null);
        $insideWaitingPeriod = $nextEligibleDate !== null
            && $nextEligibleDate > Carbon::today()->toDateString();

        if ($eligibility === EligibilityStatusValue::TEMPORARY_DEFERRED || $insideWaitingPeriod) {
            return ['status' => 'temporarily_deferred', 'next_eligible_date' => $nextEligibleDate];
        }

        if ($eligibility === EligibilityStatusValue::ELIGIBLE) {
            return ['status' => 'available', 'next_eligible_date' => null];
        }

        return ['status' => 'not_available', 'next_eligible_date' => null];
    }

    private function normalizedDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $availability */
    private function aggregateAvailabilityStatus(array $availability): string
    {
        if ((int) ($availability['available_donors'] ?? 0) > 0) {
            return 'available';
        }
        if ((int) ($availability['temporarily_deferred_donors'] ?? 0) > 0) {
            return 'temporarily_deferred';
        }

        return 'not_available';
    }

    /** @param array<string, array<string, mixed>> $availability */
    private function bestAvailabilityStatus(array $availability): string
    {
        $statuses = array_map(
            static fn (string $bloodType): string => (string) ($availability[$bloodType]['status'] ?? 'not_available'),
            self::SUPPORTED_BLOOD_TYPES
        );

        if (in_array('available', $statuses, true)) {
            return 'available';
        }
        if (in_array('temporarily_deferred', $statuses, true)) {
            return 'temporarily_deferred';
        }

        return 'not_available';
    }

    /**
     * @param  array<int, string>  $missingFields
     * @return array<string, mixed>
     */
    private function unmappedDonorPayload(
        int $donorId,
        ?string $barangay,
        ?string $city,
        string $bloodType,
        string $confidence,
        array $missingFields,
        ?int $locationId = null
    ): array {
        return [
            'donor_reference' => 'D'.$donorId,
            'location_id' => $locationId,
            'barangay_name' => $barangay ?? 'Not recorded',
            'city' => $city ?? 'Not recorded',
            'blood_type' => $bloodType,
            'blood_type_confidence' => $confidence,
            'missing_fields' => array_values(array_unique($missingFields)),
        ];
    }

    /**
     * Resolve curated legacy centers when the local boundary dataset has no
     * unique locality match. Donor or facility coordinates are never used.
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
