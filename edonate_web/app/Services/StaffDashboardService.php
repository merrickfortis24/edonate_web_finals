<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StaffDashboardService
{
    /** @return array{metrics: array<string, array{value:int|null,available:bool}>, activities: array<int, array<string, string>>, activity_available: bool} */
    public function build(): array
    {
        $today = now()->toDateString();
        $activity = $this->recentActivities();

        return [
            'metrics' => [
                'appointments_today' => $this->countMetric('appointments', ['appointment_id', 'appointment_date'], fn () => DB::table('appointments')->whereDate('appointment_date', $today)->count()),
                'confirmed_today' => $this->countMetric('appointments', ['appointment_id', 'appointment_date', 'status'], fn () => DB::table('appointments')->whereDate('appointment_date', $today)->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('confirmed', 'approved', 'scheduled', 'rescheduled')")->count()),
                'checked_in_today' => $this->countMetric('appointments', ['appointment_id', 'appointment_date', 'status'], fn () => DB::table('appointments')->whereDate('appointment_date', $today)->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('checked_in', 'checked in')")->count()),
                'completed_today' => $this->countMetric('donation_records', ['donation_id', 'donation_date', 'donation_status'], fn () => DB::table('donation_records')->whereDate('donation_date', $today)->whereRaw("LOWER(TRIM(COALESCE(donation_status, ''))) = 'completed'")->count()),
                'deferred_today' => $this->countMetric('appointments', ['appointment_id', 'appointment_date', 'status'], fn () => DB::table('appointments')->whereDate('appointment_date', $today)->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'deferred_on_site'")->count()),
                'open_requests' => $this->countMetric('blood_requests', ['request_id', 'status'], fn () => DB::table('blood_requests')->whereIn('status', ['open', 'in_progress'])->count()),
                'emergency_requests' => $this->countMetric('blood_requests', ['request_id', 'status', 'urgency'], fn () => DB::table('blood_requests')->whereIn('status', ['open', 'in_progress'])->whereRaw("LOWER(TRIM(COALESCE(urgency, ''))) = 'emergency'")->count()),
                'active_facilities' => $this->countMetric('facilities', ['facility_id', 'status'], fn () => DB::table('facilities')->whereRaw("LOWER(TRIM(COALESCE(status, 'active'))) = 'active'")->count()),
                'inventory_alerts' => $this->inventoryAlertMetric(),
            ],
            'activities' => $activity['items'],
            'activity_available' => $activity['available'],
        ];
    }

    /** @param array<int, string> $columns
     *  @param callable(): int $query
     *  @return array{value:int|null,available:bool}
     */
    private function countMetric(string $table, array $columns, callable $query): array
    {
        if (! $this->hasColumns($table, $columns)) {
            return ['value' => null, 'available' => false];
        }

        try {
            return ['value' => (int) $query(), 'available' => true];
        } catch (Throwable $exception) {
            report($exception);

            return ['value' => null, 'available' => false];
        }
    }

    /** @return array{value:int|null,available:bool} */
    private function inventoryAlertMetric(): array
    {
        if (! $this->hasColumns('facility_blood_inventory', ['facility_id', 'available_units', 'low_stock_threshold'])) {
            return ['value' => null, 'available' => false];
        }

        try {
            return [
                'value' => (int) DB::table('facility_blood_inventory')
                    ->whereRaw('COALESCE(available_units, 0) <= COALESCE(low_stock_threshold, ?)', [config('blood_inventory.default_low_stock_threshold', 5)])
                    ->count(),
                'available' => true,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['value' => null, 'available' => false];
        }
    }

    /** @return array{items: array<int, array<string, string>>, available: bool} */
    private function recentActivities(): array
    {
        if (! $this->hasColumns('audit_logs', ['action_type', 'description', 'created_at'])) {
            return ['items' => [], 'available' => false];
        }

        try {
            $columns = ['action_type', 'description', 'created_at'];
            if (Schema::hasColumn('audit_logs', 'actor_name')) {
                $columns[] = 'actor_name';
            }

            $items = DB::table('audit_logs')->orderByDesc('created_at')->limit(5)->get($columns)
                ->map(static fn (object $row): array => [
                    'title' => trim((string) ($row->action_type ?? '')) !== '' ? str($row->action_type)->replace('_', ' ')->title()->toString() : 'System activity',
                    'description' => trim((string) ($row->description ?? '')),
                    'actor' => trim((string) ($row->actor_name ?? '')),
                    'created_at' => (string) ($row->created_at ?? ''),
                ])->all();

            return ['items' => $items, 'available' => true];
        } catch (Throwable $exception) {
            report($exception);

            return ['items' => [], 'available' => false];
        }
    }

    /** @param array<int, string> $columns */
    private function hasColumns(string $table, array $columns): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumns($table, $columns);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
