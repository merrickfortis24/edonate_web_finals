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

        return view('admin.report_analytics', [
            'reportPayload' => $this->safeBuild($filters),
            'reportApi' => [
                'dataUrl' => route('admin.report-analytics.data'),
                'exportUrl' => route('admin.report-analytics.export'),
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
        $fileName = 'edonate-report-' . date('Ymd-His') . '.csv';

        $reportError = ! empty($payload['error']);
        $snapshotAt = (string) ($payload['inventory_snapshot_at'] ?? '');

        return response()->streamDownload(function () use ($period, $summary, $trend, $inventory, $distribution, $reportError, $snapshotAt): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['eDonate aggregate report']);
            fputcsv($handle, ['Period', (string) ($period['label'] ?? '')]);
            fputcsv($handle, ['Start date', (string) ($period['start'] ?? '')]);
            fputcsv($handle, ['End date', (string) ($period['end'] ?? '')]);
            fputcsv($handle, ['Report status', $reportError ? 'Unavailable - please retry later' : 'Available']);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary metric', 'Value']);
            foreach ($summary as $metric => $value) {
                if (is_scalar($value)) {
                    fputcsv($handle, [$this->labelize((string) $metric), $value]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Trend period', 'Donors registered', 'Completed donations']);
            $labels = is_array($trend['labels'] ?? null) ? $trend['labels'] : [];
            $donors = is_array($trend['donors'] ?? null) ? $trend['donors'] : [];
            $donations = is_array($trend['donations'] ?? null) ? $trend['donations'] : [];
            foreach ($labels as $index => $label) {
                fputcsv($handle, [
                    (string) $label,
                    (int) ($donors[$index] ?? 0),
                    (int) ($donations[$index] ?? 0),
                ]);
            }

            fputcsv($handle, []);
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
                'trend' => ['labels' => [], 'donors' => [], 'donations' => [], 'granularity' => 'day'],
                'distribution' => ['basis' => 'verified', 'verified_total' => null, 'self_reported_total' => null, 'unknown_total' => null, 'items' => []],
                'inventory' => [],
                'inventory_snapshot_at' => null,
                'error' => true,
                'error_message' => 'Report data is temporarily unavailable. Please try again later.',
            ];
        }
    }
}
