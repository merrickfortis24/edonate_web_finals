<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Database-backed report aggregates used by the admin Reports page.
 *
 * The service intentionally returns aggregate values only. It does not expose
 * donor emails, contact numbers, exact addresses, verification documents, or
 * eligibility answers to the report UI/export.
 */
class ReportService
{
    private const RANGES = ['today', 'week', 'month', 'year', 'custom'];

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters);
        $facilityId = $this->positiveInteger($filters['facility_id'] ?? null);
        $bloodTypeId = $this->positiveInteger($filters['blood_type_id'] ?? null);

        return [
            'period' => $period,
            'summary' => $this->summary($period['start'], $period['end'], $facilityId, $bloodTypeId),
            'availability' => ['summary' => $this->summaryAvailability($facilityId)],
            'trend' => $this->trend($period['start'], $period['end'], $facilityId, $bloodTypeId),
            'distribution' => $this->distribution($period['start'], $period['end'], $bloodTypeId),
            'inventory' => $this->inventory($facilityId),
            'inventory_snapshot_at' => now()->toIso8601String(),
            'filters' => [
                'range' => $period['range'],
                'start_date' => $period['start'],
                'end_date' => $period['end'],
                'facility_id' => $facilityId,
                'blood_type_id' => $bloodTypeId,
                'facilities' => $this->facilityOptions(),
                'blood_types' => $this->bloodTypeOptions(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{range:string,start:string,end:string,label:string}
     */
    public function resolvePeriod(array $filters = []): array
    {
        $range = strtolower(trim((string) ($filters['range'] ?? 'year')));
        if (! in_array($range, self::RANGES, true)) {
            $range = 'month';
        }

        $today = Carbon::today();
        $start = match ($range) {
            'today' => $today->copy(),
            'week' => $today->copy()->startOfWeek(Carbon::MONDAY),
            'year' => $today->copy()->startOfYear(),
            'custom' => Carbon::parse((string) ($filters['start_date'] ?? $today->copy()->startOfMonth()->toDateString())),
            default => $today->copy()->startOfMonth(),
        };
        $end = match ($range) {
            'today' => $today->copy(),
            'week' => $today->copy()->endOfWeek(Carbon::SUNDAY),
            'year' => $today->copy()->endOfYear(),
            'custom' => Carbon::parse((string) ($filters['end_date'] ?? $today->toDateString())),
            default => $today->copy()->endOfMonth(),
        };

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        if ($range === 'custom') {
            return [
                'range' => $range,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => $start->format('M j, Y') . ' - ' . $end->format('M j, Y'),
            ];
        }

        return [
            'range' => $range,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => match ($range) {
                'today' => 'Today',
                'week' => 'This week',
                'year' => 'This year',
                'custom' => $start->format('M j, Y') . ' – ' . $end->format('M j, Y'),
                default => $today->format('F Y'),
            },
        ];
    }

    /**
     * @return array<int, array{value:int,label:string}>
     */
    public function facilityOptions(): array
    {
        if (! $this->hasTable('facilities') || ! $this->hasColumns('facilities', ['facility_id', 'facility_name'])) {
            return [];
        }

        try {
            return DB::table('facilities')
                ->select(['facility_id', 'facility_name'])
                ->orderBy('facility_name')
                ->get()
                ->map(static fn (object $row): array => [
                    'value' => (int) $row->facility_id,
                    'label' => (string) $row->facility_name,
                ])
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return array<int, array{value:int,label:string}>
     */
    public function bloodTypeOptions(): array
    {
        if (! $this->hasTable('blood_types') || ! $this->hasColumns('blood_types', ['blood_type_id', 'blood_type'])) {
            return [];
        }

        try {
            $order = array_flip(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);

            return DB::table('blood_types')
                ->select(['blood_type_id', 'blood_type'])
                ->orderBy('blood_type')
                ->get()
                ->sortBy(static fn (object $row): int => $order[(string) $row->blood_type] ?? 999)
                ->map(static fn (object $row): array => [
                    'value' => (int) $row->blood_type_id,
                    'label' => (string) $row->blood_type,
                ])
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return array<string, int|float>
     */
    private function summary(string $start, string $end, ?int $facilityId, ?int $bloodTypeId): array
    {
        $completedDonations = $this->completedDonationQuery($start, $end, $facilityId, $bloodTypeId)->count();
        $allDonationRecords = $this->donationQuery($start, $end, $facilityId, $bloodTypeId)->count();
        $deferredDonations = $this->donationQuery($start, $end, $facilityId, $bloodTypeId, ['deferred'])->count();
        $failedDonations = $this->donationQuery($start, $end, $facilityId, $bloodTypeId, ['failed'])->count();

        $donorQuery = $this->donorQuery($start, $end, $bloodTypeId);
        // Identity verification and blood-type verification are separate
        // concepts. The summary card counts verified donor accounts; the
        // distribution chart below applies the blood_type_status filter.
        $verifiedDonorQuery = $this->donorQuery($start, $end, $bloodTypeId);
        if ($this->hasColumn('donors', 'verification_status')) {
            $verifiedDonorQuery->whereRaw("LOWER(COALESCE(d.verification_status, '')) = ?", ['verified']);
        } elseif ($this->hasColumn('donors', 'blood_type_status')) {
            $verifiedDonorQuery->whereRaw("LOWER(COALESCE(d.blood_type_status, '')) = ?", ['verified']);
        }
        $verifiedDonors = $verifiedDonorQuery->count();
        $donorTotal = $donorQuery->count();
        $eligibleDonors = $this->latestEligibilityQuery('eligible')->count();

        $appointments = $this->appointmentQuery($start, $end, $facilityId, $bloodTypeId);
        $upcomingAppointments = $this->appointmentQuery($start, $end, $facilityId, $bloodTypeId, ['confirmed', 'pending'])
            ->whereDate('ap.appointment_date', '>=', Carbon::today()->toDateString())
            ->count();
        $noShows = $this->appointmentQuery($start, $end, $facilityId, $bloodTypeId, ['no_show'])->count();
        $onSiteDeferred = $this->appointmentQuery($start, $end, $facilityId, $bloodTypeId, ['deferred_on_site'])->count();

        $requestSummary = $this->requestSummary($start, $end, $facilityId, $bloodTypeId);
        $inventorySummary = $this->inventorySummary($facilityId);

        $denominator = $completedDonations + $deferredDonations + $failedDonations;

        return [
            'total_donations' => $allDonationRecords,
            'completed_donations' => $completedDonations,
            'deferred_donations' => $deferredDonations,
            'failed_donations' => $failedDonations,
            'success_rate' => $denominator > 0 ? round(($completedDonations / $denominator) * 100, 1) : 0,
            'verified_donors' => $verifiedDonors,
            'donors_in_period' => $donorTotal,
            'eligible_donors' => $eligibleDonors,
            'upcoming_appointments' => $upcomingAppointments,
            'appointments_in_period' => $appointments->count(),
            'no_shows' => $noShows,
            'deferred_on_site' => $onSiteDeferred,
            'open_requests' => $requestSummary['open'],
            'emergency_requests' => $requestSummary['emergency'],
            'fulfilled_requests' => $requestSummary['fulfilled'],
            'events_in_period' => $this->eventCount($start, $end, $facilityId),
            'low_stock_blood_types' => $inventorySummary['low'],
            'out_of_stock_blood_types' => $inventorySummary['out_of_stock'],
            'total_inventory_units' => $inventorySummary['units'],
            'pending_verification' => $this->countDonorsByVerification('pending'),
            'verified_donor_accounts' => $this->countDonorsByVerification('verified'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trend(string $start, string $end, ?int $facilityId, ?int $bloodTypeId): array
    {
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        $daily = $startDate->diffInDays($endDate) <= 31;
        $labels = [];
        $buckets = [];
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $key = $daily ? $cursor->toDateString() : $cursor->format('Y-m');
            $labels[] = $daily ? $cursor->format('M j') : $cursor->format('M Y');
            $buckets[$key] = ['donors' => 0, 'donations' => 0];
            $daily ? $cursor->addDay() : $cursor->addMonthNoOverflow();
        }

        if ($this->hasColumns('donors', ['date_registered'])) {
            $this->donorQuery($start, $end, $bloodTypeId)
                ->get(['d.date_registered'])
                ->each(function (object $row) use (&$buckets, $daily): void {
                    if (! $row->date_registered) {
                        return;
                    }

                    $date = Carbon::parse((string) $row->date_registered);
                    $key = $daily ? $date->toDateString() : $date->format('Y-m');
                    if ($key !== '' && isset($buckets[$key])) {
                        $buckets[$key]['donors']++;
                    }
                });
        }

        $this->completedDonationQuery($start, $end, $facilityId, $bloodTypeId)
            ->get(['dr.donation_date'])
            ->each(function (object $row) use (&$buckets, $daily): void {
                if (! $row->donation_date) {
                    return;
                }

                $date = Carbon::parse((string) $row->donation_date);
                $key = $daily ? $date->toDateString() : $date->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['donations']++;
                }
            });

        return [
            'labels' => $labels,
            'donors' => array_values(array_map(static fn (array $bucket): int => $bucket['donors'], $buckets)),
            'donations' => array_values(array_map(static fn (array $bucket): int => $bucket['donations'], $buckets)),
            'granularity' => $daily ? 'day' : 'month',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function distribution(string $start, string $end, ?int $bloodTypeId): array
    {
        if (! $this->hasColumns('donors', ['donor_id', 'blood_type_id']) || ! $this->hasColumns('blood_types', ['blood_type_id', 'blood_type'])) {
            return ['basis' => 'verified', 'verified_total' => 0, 'self_reported_total' => 0, 'unknown_total' => 0, 'items' => []];
        }

        $verified = $this->donorQuery($start, $end, $bloodTypeId, true)
            ->whereNotNull('d.blood_type_id')
            ->select(['d.blood_type_id'])
            ->get()
            ->groupBy('blood_type_id')
            ->map->count();

        $selfReportedQuery = $this->donorQuery($start, $end, $bloodTypeId)
            ->where(function ($query): void {
                if ($this->hasColumn('donors', 'blood_type_status')) {
                    $query->whereRaw("LOWER(COALESCE(d.blood_type_status, '')) <> ?", ['verified'])
                        ->orWhereNull('d.blood_type_status');
                } else {
                    $query->whereNotNull('d.blood_type_id');
                }
            });
        $selfReported = $selfReportedQuery->whereNotNull('d.blood_type_id')->select(['d.blood_type_id'])->get()->groupBy('blood_type_id')->map->count();
        $unknown = $this->donorQuery($start, $end, null)
            ->whereNull('d.blood_type_id')
            ->count();

        $typeNames = DB::table('blood_types')->pluck('blood_type', 'blood_type_id');
        $verifiedTotal = (int) $verified->sum();
        $items = collect($typeNames)
            ->map(function ($name, $id) use ($verified, $selfReported, $verifiedTotal): array {
                $count = (int) ($verified[(int) $id] ?? 0);

                return [
                    'label' => (string) $name,
                    'value' => $verifiedTotal > 0 ? round($count / $verifiedTotal, 4) : 0,
                    'count' => $count,
                    'self_reported_count' => (int) ($selfReported[(int) $id] ?? 0),
                ];
            })
            ->filter(static fn (array $item): bool => $item['count'] > 0 || $item['self_reported_count'] > 0)
            ->values()
            ->all();

        return [
            'basis' => 'verified',
            'verified_total' => $verifiedTotal,
            'self_reported_total' => (int) $selfReported->sum(),
            'unknown_total' => $unknown,
            'items' => $items,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inventory(?int $facilityId): array
    {
        $types = $this->bloodTypeOptions();
        if ($types === [] || ! $this->hasColumns('facility_blood_inventory', ['facility_id', 'blood_type_id', 'available_units'])) {
            return [];
        }

        $query = DB::table('facility_blood_inventory as i')
            ->join('blood_types as bt', 'bt.blood_type_id', '=', 'i.blood_type_id')
            ->select([
                'i.blood_type_id',
                'bt.blood_type',
                DB::raw('SUM(COALESCE(i.available_units, 0)) as available_units'),
                DB::raw('SUM(COALESCE(i.reserved_units, 0)) as reserved_units'),
                DB::raw('SUM(COALESCE(i.low_stock_threshold, 0)) as low_stock_threshold'),
            ])
            ->groupBy('i.blood_type_id', 'bt.blood_type');

        if ($facilityId !== null) {
            $query->where('i.facility_id', $facilityId);
        }

        $rows = $query->get()->keyBy('blood_type_id');
        $demands = $this->openRequestDemand($facilityId);

        return collect($types)->map(function (array $type) use ($rows, $demands): array {
            $row = $rows->get($type['value']);
            $available = max(0, (int) ($row->available_units ?? 0));
            $reserved = max(0, (int) ($row->reserved_units ?? 0));
            $threshold = max(0, (int) ($row->low_stock_threshold ?? config('blood_inventory.default_low_stock_threshold', 5)));
            $status = $this->inventoryStatus($available, $threshold);

            return [
                'blood_type_id' => $type['value'],
                'blood_type' => $type['label'],
                'available_units' => $available,
                'reserved_units' => min($reserved, $available),
                'low_stock_threshold' => $threshold,
                'status' => $status,
                'status_label' => match ($status) {
                    'out_of_stock' => 'Out of stock',
                    'low' => 'Low',
                    default => 'Available',
                },
                'open_request_demand' => (int) ($demands[$type['value']] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return array{open:int,emergency:int,fulfilled:int}
     */
    private function requestSummary(string $start, string $end, ?int $facilityId, ?int $bloodTypeId): array
    {
        if (! $this->hasTable('blood_requests')) {
            return ['open' => 0, 'emergency' => 0, 'fulfilled' => 0];
        }

        $base = DB::table('blood_requests as br')
            ->whereDate('br.created_at', '>=', $start)
            ->whereDate('br.created_at', '<=', $end);

        if ($facilityId !== null && $this->hasColumn('blood_requests', 'facility_id')) {
            $base->where('br.facility_id', $facilityId);
        } elseif ($facilityId !== null) {
            $base->whereRaw('1 = 0');
        }
        if ($bloodTypeId !== null && $this->hasColumn('blood_requests', 'needed_blood_type_id')) {
            $base->where('br.needed_blood_type_id', $bloodTypeId);
        }

        $openQuery = clone $base;
        $fulfilledQuery = clone $base;
        $emergencyQuery = clone $base;

        return [
            'open' => (int) $openQuery->whereIn('br.status', ['open', 'in_progress'])->count(),
            'emergency' => (int) $emergencyQuery->where('br.urgency', 'emergency')->whereIn('br.status', ['open', 'in_progress'])->count(),
            'fulfilled' => (int) $fulfilledQuery->where('br.status', 'fulfilled')->count(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function openRequestDemand(?int $facilityId): array
    {
        if (! $this->hasColumns('blood_requests', ['needed_blood_type_id', 'status'])) {
            return [];
        }

        $query = DB::table('blood_requests')
            ->select('needed_blood_type_id', DB::raw('SUM(COALESCE(required_donors, total_donors_needed, 1)) as total'))
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('needed_blood_type_id')
            ->groupBy('needed_blood_type_id');

        if ($facilityId !== null && $this->hasColumn('blood_requests', 'facility_id')) {
            $query->where('facility_id', $facilityId);
        } elseif ($facilityId !== null) {
            $query->whereRaw('1 = 0');
        }

        return $query->pluck('total', 'needed_blood_type_id')->map(static fn ($value): int => (int) $value)->all();
    }

    private function eventCount(string $start, string $end, ?int $facilityId): int
    {
        if (! $this->hasColumns('donation_events', ['event_id', 'event_date'])) {
            return 0;
        }

        $query = DB::table('donation_events as ev')
            ->whereDate('ev.event_date', '>=', $start)
            ->whereDate('ev.event_date', '<=', $end);

        if ($facilityId !== null) {
            if ($this->hasColumn('donation_events', 'facility_id')) {
                $query->where('ev.facility_id', $facilityId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return (int) $query->count();
    }

    private function countDonorsByVerification(string $status): int
    {
        if (! $this->hasColumns('donors', ['verification_status'])) {
            return 0;
        }

        return (int) DB::table('donors')->whereRaw("LOWER(COALESCE(verification_status, '')) = ?", [strtolower($status)])->count();
    }

    private function latestEligibilityQuery(?string $status = null)
    {
        if (! $this->hasColumns('eligibility_status', ['eligibility_id', 'donor_id', 'status'])) {
            return DB::query()->fromRaw('(select 1 where 0) as empty_eligibility');
        }

        $latest = DB::table('eligibility_status')
            ->selectRaw('MAX(eligibility_id) as latest_eligibility_id')
            ->groupBy('donor_id');

        $query = DB::table('eligibility_status as es')
            ->whereIn('es.eligibility_id', $latest);

        if ($status !== null) {
            $query->whereRaw("LOWER(COALESCE(es.status, '')) = ?", [strtolower($status)]);
        }

        return $query;
    }

    private function donorQuery(string $start, string $end, ?int $bloodTypeId = null, ?bool $verifiedType = null)
    {
        if (! $this->hasColumns('donors', ['donor_id', 'date_registered'])) {
            return DB::query()->fromRaw('(select null as donor_id, null as date_registered where 1 = 0) as d');
        }

        $query = DB::table('donors as d')
            ->whereDate('d.date_registered', '>=', $start)
            ->whereDate('d.date_registered', '<=', $end);

        if ($bloodTypeId !== null) {
            $query->where('d.blood_type_id', $bloodTypeId);
        }

        if ($verifiedType === true && $this->hasColumn('donors', 'blood_type_status')) {
            $query->whereRaw("LOWER(COALESCE(d.blood_type_status, '')) = ?", ['verified']);
        }

        return $query;
    }

    private function appointmentQuery(string $start, string $end, ?int $facilityId, ?int $bloodTypeId, array $statuses = [])
    {
        if (! $this->hasColumns('appointments', ['appointment_id', 'appointment_date', 'status'])) {
            return DB::query()->fromRaw('(select null as appointment_id where 1 = 0) as ap');
        }

        $query = DB::table('appointments as ap')
            ->whereDate('ap.appointment_date', '>=', $start)
            ->whereDate('ap.appointment_date', '<=', $end);

        if ($statuses !== []) {
            $query->whereIn('ap.status', $statuses);
        }

        if ($facilityId !== null && $this->hasColumns('appointments', ['event_id'])
            && $this->hasColumns('donation_events', ['event_id'])) {
            $query->join('donation_events as ev', 'ev.event_id', '=', 'ap.event_id');
            if ($this->hasColumn('donation_events', 'facility_id')) {
                $query->where('ev.facility_id', $facilityId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($bloodTypeId !== null && $this->hasColumns('donors', ['donor_id', 'blood_type_id'])) {
            $query->join('donors as d', 'd.donor_id', '=', 'ap.donor_id')
                ->where('d.blood_type_id', $bloodTypeId);
        }

        return $query;
    }

    private function donationQuery(string $start, string $end, ?int $facilityId, ?int $bloodTypeId, array $statuses = [])
    {
        if (! $this->hasColumns('donation_records', ['donation_id', 'donation_date'])) {
            return DB::query()->fromRaw('(select null as donation_id where 1 = 0) as dr');
        }

        $query = DB::table('donation_records as dr')
            ->whereDate('dr.donation_date', '>=', $start)
            ->whereDate('dr.donation_date', '<=', $end);

        if ($statuses !== [] && $this->hasColumn('donation_records', 'donation_status')) {
            $query->whereIn('dr.donation_status', $statuses);
        }

        if (($facilityId !== null || $bloodTypeId !== null)
            && $this->hasColumns('appointments', ['appointment_id', 'event_id'])
            && $this->hasColumns('donation_events', ['event_id'])) {
            $query->join('appointments as ap', 'ap.appointment_id', '=', 'dr.appointment_id')
                ->join('donation_events as ev', 'ev.event_id', '=', 'ap.event_id');

            if ($facilityId !== null) {
                if ($this->hasColumn('donation_events', 'facility_id')) {
                    $query->where('ev.facility_id', $facilityId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        if ($bloodTypeId !== null && $this->hasColumns('donors', ['donor_id', 'blood_type_id'])) {
            if (! $this->hasColumns('appointments', ['appointment_id', 'event_id'])) {
                $query->join('donors as d', 'd.donor_id', '=', 'dr.donor_id');
            } elseif (! $this->hasColumn('donation_records', 'verified_blood_type_id')) {
                $query->join('donors as d', 'd.donor_id', '=', 'dr.donor_id');
            } else {
                $query->join('donors as d', 'd.donor_id', '=', 'dr.donor_id');
                $query->whereRaw('COALESCE(dr.verified_blood_type_id, d.blood_type_id) = ?', [$bloodTypeId]);
            }
        }

        return $query;
    }

    private function completedDonationQuery(string $start, string $end, ?int $facilityId, ?int $bloodTypeId)
    {
        $query = $this->donationQuery($start, $end, $facilityId, $bloodTypeId);

        if ($this->hasColumn('donation_records', 'donation_status')) {
            return $query->whereRaw("LOWER(COALESCE(dr.donation_status, '')) = ?", ['completed']);
        }

        return $query;
    }

    /**
     * @return array{units:int,low:int,out_of_stock:int}
     */
    private function inventorySummary(?int $facilityId): array
    {
        if (! $this->hasColumns('facility_blood_inventory', ['facility_id', 'blood_type_id', 'available_units'])) {
            return ['units' => 0, 'low' => 0, 'out_of_stock' => 0];
        }

        $query = DB::table('facility_blood_inventory')
            ->select(['available_units', 'low_stock_threshold']);

        if ($facilityId !== null) {
            $query->where('facility_id', $facilityId);
        }

        $rows = $query->get();
        $units = 0;
        $low = 0;
        $outOfStock = 0;

        foreach ($rows as $row) {
            $available = max(0, (int) ($row->available_units ?? 0));
            $threshold = max(0, (int) ($row->low_stock_threshold ?? config('blood_inventory.default_low_stock_threshold', 5)));
            $units += $available;

            match ($this->inventoryStatus($available, $threshold)) {
                'out_of_stock' => $outOfStock++,
                'low' => $low++,
                default => null,
            };
        }

        return [
            'units' => $units,
            'low' => $low,
            'out_of_stock' => $outOfStock,
        ];
    }

    private function inventoryStatus(int $available, int $threshold): string
    {
        return match (true) {
            $available <= 0 => 'out_of_stock',
            $available <= max(0, $threshold) => 'low',
            default => 'available',
        };
    }

    /**
     * Distinguish a real zero from an unavailable metric caused by an
     * incomplete legacy schema. Query failures are handled at the controller
     * boundary and return the same unavailable state without leaking SQL.
     *
     * @return array<string, bool>
     */
    private function summaryAvailability(?int $facilityId): array
    {
        $donations = $this->hasColumns('donation_records', ['donation_id', 'donation_date']);
        $donors = $this->hasColumns('donors', ['donor_id', 'date_registered']);
        $appointments = $this->hasColumns('appointments', ['appointment_id', 'appointment_date', 'status']);
        $requests = $this->hasColumns('blood_requests', ['request_id', 'status', 'created_at']);
        $inventory = $this->hasColumns('facility_blood_inventory', ['facility_id', 'blood_type_id', 'available_units']);
        $eligibility = $this->hasColumns('eligibility_status', ['eligibility_id', 'donor_id', 'status']);
        $verifiedDonorField = $this->hasColumn('donors', 'verification_status')
            || $this->hasColumn('donors', 'blood_type_status');
        $eventFacilityAttribution = $facilityId === null || $this->hasColumn('donation_events', 'facility_id');
        $requestFacilityAttribution = $facilityId === null || $this->hasColumn('blood_requests', 'facility_id');
        $donorFacilityAttribution = $facilityId === null;

        return [
            'total_donations' => $donations && $eventFacilityAttribution,
            'completed_donations' => $donations && $eventFacilityAttribution,
            'deferred_donations' => $donations && $eventFacilityAttribution,
            'failed_donations' => $donations && $eventFacilityAttribution,
            'success_rate' => $donations && $eventFacilityAttribution,
            'verified_donors' => $donors && $verifiedDonorField && $donorFacilityAttribution,
            'donors_in_period' => $donors && $donorFacilityAttribution,
            'eligible_donors' => $eligibility && $donorFacilityAttribution,
            'upcoming_appointments' => $appointments && $eventFacilityAttribution,
            'appointments_in_period' => $appointments && $eventFacilityAttribution,
            'no_shows' => $appointments && $eventFacilityAttribution,
            'deferred_on_site' => $appointments && $eventFacilityAttribution,
            'open_requests' => $requests && $requestFacilityAttribution,
            'emergency_requests' => $requests && $requestFacilityAttribution && $this->hasColumn('blood_requests', 'urgency'),
            'fulfilled_requests' => $requests && $requestFacilityAttribution,
            'events_in_period' => $this->hasColumns('donation_events', ['event_id', 'event_date']) && $eventFacilityAttribution,
            'low_stock_blood_types' => $inventory,
            'out_of_stock_blood_types' => $inventory,
            'total_inventory_units' => $inventory,
            'pending_verification' => $this->hasColumn('donors', 'verification_status'),
            'verified_donor_accounts' => $this->hasColumn('donors', 'verification_status'),
        ];
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /** @param array<int, string> $columns */
    private function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! $this->hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

}
