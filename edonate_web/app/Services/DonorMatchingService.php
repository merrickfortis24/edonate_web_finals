<?php

namespace App\Services;

use App\Models\BloodRequest;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DonorMatchingService
{
    /** @return Collection<int, object> */
    public function exactMatches(BloodRequest $request, int $limit = 100): Collection
    {
        if ((int) $request->needed_blood_type_id <= 0) {
            return collect();
        }

        return $this->baseCandidateQuery($request)
            ->where('d.blood_type_id', (int) $request->needed_blood_type_id)
            ->whereRaw("LOWER(TRIM(COALESCE(d.blood_type_status, ''))) = ?", ['verified'])
            ->orderBy('location_rank')
            ->orderBy('d.last_name')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): object => $this->withMatchType($row, 'exact'));
    }

    /** @return Collection<int, object> */
    public function replacementAnyMatches(BloodRequest $request, int $limit = 100): Collection
    {
        if (! $request->allowsOtherBloodTypes()) {
            return collect();
        }

        $query = $this->baseCandidateQuery($request);

        if ((int) $request->needed_blood_type_id > 0) {
            $query->where(function (Builder $builder) use ($request): void {
                $builder->where('d.blood_type_id', '<>', (int) $request->needed_blood_type_id)
                    ->orWhereNull('d.blood_type_id')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(d.blood_type_status, ''))) <> ?", ['verified']);
            });
        }

        return $query
            ->orderBy('location_rank')
            ->orderBy('d.last_name')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): object => $this->withMatchType($row, 'replacement_any'));
    }

    /** @return array<string, mixed> */
    public function summary(BloodRequest $request): array
    {
        $exact = $this->exactMatches($request, 500);
        $other = $this->replacementAnyMatches($request, 500);
        $responses = DB::table('blood_request_donors')
            ->where('request_id', $request->request_id)
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'exact_matches_found' => $exact->count(),
            'other_eligible_candidates' => $other->count(),
            'notified' => (int) (($responses['notified'] ?? 0) + ($responses['contacted'] ?? 0)),
            'interested' => (int) (($responses['interested'] ?? 0) + ($responses['responded'] ?? 0)),
            'declined' => (int) ($responses['declined'] ?? 0),
            'confirmed' => (int) (($responses['confirmed'] ?? 0) + ($responses['scheduled'] ?? 0)),
            'completed' => (int) (($responses['completed'] ?? 0) + ($responses['donated'] ?? 0)),
        ];
    }

    public function isFulfilled(BloodRequest $request): bool
    {
        $completedStatuses = ['completed', 'donated'];
        $completed = (int) DB::table('blood_request_donors')
            ->where('request_id', $request->request_id)
            ->whereIn('status', $completedStatuses)
            ->count();

        $exactCompleted = (int) DB::table('blood_request_donors')
            ->where('request_id', $request->request_id)
            ->where('match_type', 'exact')
            ->whereIn('status', $completedStatuses)
            ->count();

        return $completed >= $request->requiredDonors()
            && $exactCompleted >= $request->specificMatchesRequired();
    }

    private function baseCandidateQuery(BloodRequest $request): Builder
    {
        $latestEligibility = DB::table('eligibility_status')
            ->select('donor_id', DB::raw('MAX(eligibility_id) AS latest_eligibility_id'))
            ->whereNotNull('donor_id')
            ->groupBy('donor_id');

        $facility = $request->facility;
        $facilityBarangay = strtolower(trim((string) ($facility?->barangay_name ?? '')));
        $facilityCity = strtolower(trim((string) ($facility?->city ?? '')));

        $query = DB::table('donors AS d')
            ->leftJoin('blood_types AS bt', 'bt.blood_type_id', '=', 'd.blood_type_id')
            ->joinSub($latestEligibility, 'es_latest', function ($join): void {
                $join->on('es_latest.donor_id', '=', 'd.donor_id');
            })
            ->join('eligibility_status AS es', 'es.eligibility_id', '=', 'es_latest.latest_eligibility_id')
            ->leftJoin('locations AS l', 'l.location_id', '=', 'd.location_id')
            ->whereRaw("LOWER(TRIM(COALESCE(d.verification_status, ''))) = ?", ['verified'])
            ->whereRaw("LOWER(TRIM(COALESCE(es.status, ''))) = ?", ['eligible'])
            ->where(function (Builder $query): void {
                $query->whereNull('es.next_eligible_date')
                    ->orWhereDate('es.next_eligible_date', '<=', Carbon::today()->toDateString());
            })
            ->whereNotNull('l.barangay_name')
            ->whereRaw("TRIM(COALESCE(l.barangay_name, '')) <> ''")
            ->whereNotExists(fn (Builder $query): Builder => $this->activeAppointmentSubquery($query))
            ->whereNotExists(function (Builder $query) use ($request): void {
                $query->select(DB::raw('1'))
                    ->from('blood_request_donors AS brd')
                    ->whereColumn('brd.donor_id', 'd.donor_id')
                    ->where('brd.request_id', (int) $request->request_id);
            })
            ->select(
                'd.donor_id',
                'd.first_name',
                'd.last_name',
                'd.blood_type_status',
                'bt.blood_type',
                'l.barangay_name',
                'l.city',
                'l.province',
                'es.status AS eligibility_status',
                DB::raw($this->locationRankSql($facilityBarangay, $facilityCity) . ' AS location_rank')
            );

        if (Schema::hasColumn('donors', 'is_active')) {
            $query->where('d.is_active', true);
        }

        return $query;
    }

    private function activeAppointmentSubquery(Builder $query): Builder
    {
        return $query
            ->select(DB::raw('1'))
            ->from('appointments AS ap')
            ->whereColumn('ap.donor_id', 'd.donor_id')
            ->whereIn(DB::raw("LOWER(TRIM(COALESCE(ap.status, '')))"), ['confirmed', 'checked_in'])
            ->whereDate('ap.appointment_date', '>=', Carbon::today()->toDateString());
    }

    private function locationRankSql(string $facilityBarangay, string $facilityCity): string
    {
        $barangay = str_replace("'", "''", $facilityBarangay);
        $city = str_replace("'", "''", $facilityCity);

        return "CASE WHEN LOWER(TRIM(COALESCE(l.barangay_name, ''))) = '{$barangay}' THEN 0 "
            . "WHEN LOWER(TRIM(COALESCE(l.city, ''))) = '{$city}' THEN 1 ELSE 2 END";
    }

    private function withMatchType(object $row, string $matchType): object
    {
        $row->match_type = $matchType;
        return $row;
    }
}
