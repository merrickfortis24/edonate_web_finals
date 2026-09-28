<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $exportFilters = array_filter($filters, static fn ($value): bool => $value !== null);

        return view('admin.report_analytics', [
            'reportPayload' => $this->safeBuild($filters),
            'reportApi' => [
                'dataUrl' => route('admin.report-analytics.data'),
                'exportUrl' => route('admin.report-analytics.export'),
                'initialExportUrl' => route('admin.report-analytics.export', $exportFilters),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $payload = $this->safeBuild($this->validatedFilters($request));

        return response()->json($payload, ! empty($payload['error']) ? 503 : 200);
    }

    public function export(Request $request): StreamedResponse
    {
        $payload = $this->safeBuild($this->validatedFilters($request));
        $period = $payload['period'] ?? [];
        $summary = $payload['summary'] ?? [];
        $trend = $payload['trend'] ?? [];
        $inventory = $payload['inventory'] ?? [];
        $distribution = $payload['distribution'] ?? [];
        $summaryAvailability = $payload['availability']['summary'] ?? [];
        $filters = $payload['filters'] ?? [];
        $facility = collect($filters['facilities'] ?? [])->firstWhere('value', (int) ($filters['facility_id'] ?? 0));
        $bloodType = collect($filters['blood_types'] ?? [])->firstWhere('value', (int) ($filters['blood_type_id'] ?? 0));
        $facilityLabel = $facility['label'] ?? 'All facilities';
        $bloodTypeLabel = $bloodType['label'] ?? 'All blood types';
        $fileName = 'edonate-report-' . date('Ymd-His') . '.csv';

        $reportError = ! empty($payload['error']);
        $snapshotAt = (string) ($payload['inventory_snapshot_at'] ?? '');

        return response()->streamDownload(function () use ($period, $summary, $trend, $inventory, $distribution, $summaryAvailability, $reportError, $snapshotAt, $facilityLabel, $bloodTypeLabel): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['eDonate aggregate report']);
            fputcsv($handle, ['Period', (string) ($period['label'] ?? '')]);
            fputcsv($handle, ['Start date', (string) ($period['start'] ?? '')]);
            fputcsv($handle, ['End date', (string) ($period['end'] ?? '')]);
            fputcsv($handle, ['Facility filter', $facilityLabel]);
            fputcsv($handle, ['Blood type filter', $bloodTypeLabel]);
            fputcsv($handle, ['Report status', $reportError ? 'Unavailable - please retry later' : 'Available']);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary metric', 'Value']);
            foreach ($summary as $metric => $value) {
                if (is_scalar($value)) {
                    $available = $summaryAvailability[$metric] ?? true;
                    fputcsv($handle, [$this->labelize((string) $metric), $available ? $value : 'N/A']);
                }
            }

            fputcsv($handle, []);
            $donorTrendAvailable = ! empty($trend['donors_available']);
            fputcsv($handle, ['Donor trend status', $donorTrendAvailable ? 'Available' : 'Unavailable']);
            if (! $donorTrendAvailable && ! empty($trend['donors_message'])) {
                fputcsv($handle, ['Donor trend note', (string) $trend['donors_message']]);
            }
            fputcsv($handle, ['Trend period', 'Donors registered', 'Completed donations']);
            $labels = is_array($trend['labels'] ?? null) ? $trend['labels'] : [];
            $donors = is_array($trend['donors'] ?? null) ? $trend['donors'] : [];
            $donations = is_array($trend['donations'] ?? null) ? $trend['donations'] : [];
            foreach ($labels as $index => $label) {
                fputcsv($handle, [
                    (string) $label,
                    $donorTrendAvailable ? (int) ($donors[$index] ?? 0) : 'N/A',
                    (int) ($donations[$index] ?? 0),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Blood-type distribution status', ! empty($distribution['available']) ? 'Available' : 'Unavailable']);
            if (empty($distribution['available']) && ! empty($distribution['message'])) {
                fputcsv($handle, ['Blood-type distribution note', (string) $distribution['message']]);
            }
            fputcsv($handle, ['Blood type', 'Verified donor count', 'Self-reported count']);
            foreach ((array) ($distribution['items'] ?? []) as $item) {
                fputcsv($handle, [
                    (string) ($item['label'] ?? ''),
                    (int) ($item['count'] ?? 0),
                    (int) ($item['self_reported_count'] ?? 0),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, []);
            fputcsv($handle, ['Current inventory snapshot as of', $snapshotAt]);
            fputcsv($handle, ['Blood type', 'Available units', 'Reserved units', 'Open request donor count (not units)', 'Status']);
            foreach ($inventory as $row) {
                fputcsv($handle, [
                    (string) ($row['blood_type'] ?? ''),
                    (int) ($row['available_units'] ?? 0),
                    (int) ($row['reserved_units'] ?? 0),
                    (int) ($row['open_request_demand'] ?? 0),
                    (string) ($row['status_label'] ?? ''),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'range' => ['nullable', 'string', Rule::in(['today', 'week', 'month', 'year', 'custom'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'facility_id' => ['nullable', 'integer', 'min:1'],
            'blood_type_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $range = strtolower(trim((string) ($validated['range'] ?? 'year')));
        if ($range === 'custom' && (empty($validated['start_date']) || empty($validated['end_date']))) {
            throw ValidationException::withMessages([
                'range' => 'Custom reports require both a start date and an end date.',
            ]);
        }

        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $order = Validator::make($validated, [
                'end_date' => ['after_or_equal:start_date'],
            ]);
            if ($order->fails()) {
                throw ValidationException::withMessages([
                    'end_date' => 'The end date must be on or after the start date.',
                ]);
            }
        }

        return [
            'range' => $range,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'facility_id' => $validated['facility_id'] ?? null,
            'blood_type_id' => $validated['blood_type_id'] ?? null,
        ];
    }

    private function labelize(string $value): string
    {
        return ucwords(str_replace('_', ' ', trim($value)));
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>
     */
    private function safeBuild(array $filters): array
    {
        try {
            return $this->reportService->build($filters);
        } catch (Throwable $exception) {
            report($exception);

            $period = $this->reportService->resolvePeriod($filters);
            $metricKeys = [
                'total_donations', 'completed_donations', 'deferred_donations', 'failed_donations', 'success_rate',
                'verified_donors', 'donors_in_period', 'eligible_donors', 'upcoming_appointments', 'appointments_in_period',
                'no_shows', 'deferred_on_site', 'open_requests', 'emergency_requests', 'fulfilled_requests', 'events_in_period',
                'low_stock_blood_types', 'out_of_stock_blood_types', 'total_inventory_units', 'pending_verification', 'verified_donor_accounts',
            ];

            return [
                'period' => $period,
                'summary' => array_fill_keys($metricKeys, null),
                'availability' => ['summary' => array_fill_keys($metricKeys, false)],
                'trend' => ['labels' => [], 'donors' => [], 'donors_available' => false, 'donors_message' => 'Report data is temporarily unavailable.', 'donations' => [], 'granularity' => 'day'],
                'distribution' => ['available' => false, 'message' => 'Report data is temporarily unavailable.', 'basis' => 'verified', 'verified_total' => null, 'self_reported_total' => null, 'unknown_total' => null, 'items' => []],
                'inventory' => [],
                'inventory_snapshot_at' => null,
                'filters' => [
                    'range' => $period['range'],
                    'start_date' => $period['start'],
                    'end_date' => $period['end'],
                    'facility_id' => $filters['facility_id'] ?? null,
                    'blood_type_id' => $filters['blood_type_id'] ?? null,
                    'facilities' => $this->reportService->facilityOptions(),
                    'blood_types' => $this->reportService->bloodTypeOptions(),
                ],
                'error' => true,
                'error_message' => 'Report data is temporarily unavailable. Please try again later.',
            ];
        }
    }
}
