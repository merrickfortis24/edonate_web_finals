@extends('layouts.admin')

@php
    $payload = $reportPayload ?? [];
    $period = data_get($payload, 'period', []);
    $summary = data_get($payload, 'summary', []);
    $filters = data_get($payload, 'filters', []);
    $facilities = data_get($filters, 'facilities', []);
    $bloodTypes = data_get($filters, 'blood_types', []);
    $range = data_get($filters, 'range', 'year');
@endphp

@section('title', 'eDonate - Reports & Analytics')
@section('admin_page_class', 'admin-report-analytics-page')
@section('header_title', 'Reports and Analytics')
@section('header_subtitle', 'Database-backed operational summaries with privacy-safe exports')

@section('header_actions')
    <a class="report-header-export btn" id="reportHeaderExport" href="{{ $reportApi['exportUrl'] ?? '#' }}" aria-label="Export aggregate reports">
        <i class="bi bi-download me-2" aria-hidden="true"></i>
        Export Reports
    </a>
@endsection

@section('admin_page_data')
{!! json_encode([
    'page' => 'report-analytics',
    'reportAnalytics' => $payload,
    'reportApi' => $reportApi ?? ['dataUrl' => '', 'exportUrl' => ''],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
    <main class="main container-fluid px-0">
        <div class="page-body container-fluid py-3">
            <section class="report-filter row g-3 align-items-end" role="region" aria-label="Report filters">
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label small fw-semibold" for="reportRangeFilter">Date range</label>
                    <select class="report-filter__select form-select" id="reportRangeFilter" aria-label="Filter by date range">
                        @foreach ([
                            'today' => 'Today',
                            'week' => 'This week',
                            'month' => 'This month',
                            'year' => 'This year',
                            'custom' => 'Custom range',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-xl-2 report-custom-date d-none">
                    <label class="form-label small fw-semibold" for="reportStartDate">Start date</label>
                    <input class="form-control" type="date" id="reportStartDate" value="{{ data_get($filters, 'start_date') }}" aria-label="Report start date">
                </div>
                <div class="col-6 col-md-3 col-xl-2 report-custom-date d-none">
                    <label class="form-label small fw-semibold" for="reportEndDate">End date</label>
                    <input class="form-control" type="date" id="reportEndDate" value="{{ data_get($filters, 'end_date') }}" aria-label="Report end date">
                </div>

                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label small fw-semibold" for="reportFacilityFilter">Facility</label>
                    <select class="report-filter__select form-select" id="reportFacilityFilter" aria-label="Filter by facility">
                        <option value="">All facilities</option>
                        @foreach ($facilities as $facility)
                            <option value="{{ (int) data_get($facility, 'value') }}" @selected((int) data_get($filters, 'facility_id') === (int) data_get($facility, 'value'))>{{ data_get($facility, 'label') }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label small fw-semibold" for="reportBloodTypeFilter">Blood type</label>
                    <select class="report-filter__select form-select" id="reportBloodTypeFilter" aria-label="Filter by blood type">
                        <option value="">All blood types</option>
                        @foreach ($bloodTypes as $bloodType)
                            <option value="{{ (int) data_get($bloodType, 'value') }}" @selected((int) data_get($filters, 'blood_type_id') === (int) data_get($bloodType, 'value'))>{{ data_get($bloodType, 'label') }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-xl-2 d-flex gap-2">
                    <button class="report-filter__export btn flex-grow-1" type="button" id="reportApplyBtn">Apply</button>
                    <button class="btn btn-outline-secondary" type="button" id="reportResetBtn" title="Reset report filters" aria-label="Reset report filters">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                    </button>
                </div>
            </section>

            <div class="report-period-note small text-muted mt-3" id="reportPeriodNote">
                Showing aggregate data for {{ data_get($period, 'label', 'the selected period') }}.
            </div>

            <section class="report-stats row g-3 mt-1" aria-label="Report summary metrics">
                @foreach ([
                    ['key' => 'total_donations', 'label' => 'Donation Records', 'class' => 'red'],
                    ['key' => 'active_donors', 'label' => 'Verified Donors', 'class' => 'green'],
                    ['key' => 'completed_donations', 'label' => 'Completed Donations', 'class' => 'blue'],
                    ['key' => 'success_rate', 'label' => 'Success Rate', 'class' => 'gold', 'suffix' => '%'],
                    ['key' => 'upcoming_appointments', 'label' => 'Upcoming Appointments', 'class' => 'red'],
                    ['key' => 'open_requests', 'label' => 'Open Blood Requests', 'class' => 'green'],
                    ['key' => 'low_stock_blood_types', 'label' => 'Low Stock Types', 'class' => 'blue'],
                    ['key' => 'out_of_stock_blood_types', 'label' => 'Out of Stock', 'class' => 'gold'],
                ] as $metric)
                    <div class="col-6 col-xl-3">
                        <article class="report-stat-card report-stat-card--{{ $metric['class'] }} h-100">
                            <span class="report-stat-card__label">{{ $metric['label'] }}</span>
                            <span class="report-stat-card__value" data-report-metric="{{ $metric['key'] }}">{{ number_format((float) data_get($summary, $metric['key'], 0), $metric['key'] === 'success_rate' ? 1 : 0) }}{{ $metric['suffix'] ?? '' }}</span>
                            <span class="report-stat-card__note report-stat-card__note--blue">Selected period</span>
                        </article>
                    </div>
                @endforeach
            </section>

            <section class="report-charts row g-3" aria-label="Report charts">
                <div class="col-12 col-xl-6">
                    <article class="report-chart-card h-100">
                        <h2 class="report-chart-card__title">Donors and Completed Donations</h2>
                        <div class="report-chart-card__canvas-wrap">
                            <canvas id="reportsLineChart" aria-label="Donors and completed donations trend chart"></canvas>
                        </div>
                        <div class="report-chart-card__legend" aria-label="Trend chart legend">
                            <span class="report-chart-card__legend-item"><span class="report-chart-card__dot report-chart-card__dot--red" aria-hidden="true"></span>Donors registered</span>
                            <span class="report-chart-card__legend-item"><span class="report-chart-card__dot report-chart-card__dot--pink" aria-hidden="true"></span>Completed donations</span>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-xl-6">
                    <article class="report-chart-card h-100">
                        <h2 class="report-chart-card__title">Verified Blood Type Distribution</h2>
                        <p class="report-chart-card__caption" id="reportDistributionCaption">The chart uses verified blood type records. Self-reported values are shown separately below.</p>
                        <div class="report-chart-card__canvas-wrap">
                            <canvas id="reportsBarChart" aria-label="Verified blood type distribution chart"></canvas>
                        </div>
                    </article>
                </div>
            </section>

            <section class="report-inventory-card" aria-label="Blood type inventory and demand">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h2 class="report-inventory-card__title mb-0">Blood Type Inventory &amp; Demand</h2>
                    <span class="small text-muted">Aggregated across selected facilities</span>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table report-inventory-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Blood type</th>
                                <th scope="col">Available units</th>
                                <th scope="col">Reserved</th>
                                <th scope="col">Open request demand</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody id="reportInventoryBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">Loading inventory…</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
@endsection

@push('admin_scripts')
<script>
    (function () {
        var pageData = window.AdminPageData && window.AdminPageData.reportAnalytics ? window.AdminPageData.reportAnalytics : {};
        var api = window.AdminPageData && window.AdminPageData.reportApi ? window.AdminPageData.reportApi : {};
        var dataUrl = String(api.dataUrl || '');
        var exportUrl = String(api.exportUrl || '');
        var reportPayload = pageData;
        var resizeTimer = null;

        var rangeFilter = document.getElementById('reportRangeFilter');
        var startDate = document.getElementById('reportStartDate');
        var endDate = document.getElementById('reportEndDate');
        var facilityFilter = document.getElementById('reportFacilityFilter');
        var bloodTypeFilter = document.getElementById('reportBloodTypeFilter');
        var applyButton = document.getElementById('reportApplyBtn');
        var resetButton = document.getElementById('reportResetBtn');
        var headerExport = document.getElementById('reportHeaderExport');
        var inventoryBody = document.getElementById('reportInventoryBody');
        var periodNote = document.getElementById('reportPeriodNote');
        var distributionCaption = document.getElementById('reportDistributionCaption');

        function escapeHtml(value) {
            return String(value === null || typeof value === 'undefined' ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function number(value, decimals) {
            var parsed = Number(value || 0);
            return parsed.toLocaleString(undefined, { minimumFractionDigits: decimals || 0, maximumFractionDigits: decimals || 0 });
        }

        function queryParams() {
            var params = new URLSearchParams();
            params.set('range', rangeFilter ? rangeFilter.value : 'year');
            if (rangeFilter && rangeFilter.value === 'custom') {
                if (startDate && startDate.value) params.set('start_date', startDate.value);
                if (endDate && endDate.value) params.set('end_date', endDate.value);
            }
            if (facilityFilter && facilityFilter.value) params.set('facility_id', facilityFilter.value);
            if (bloodTypeFilter && bloodTypeFilter.value) params.set('blood_type_id', bloodTypeFilter.value);
            return params;
        }

        function updateExportLink() {
            if (headerExport && exportUrl) {
                var query = queryParams().toString();
                headerExport.href = exportUrl + (query ? '?' + query : '');
            }
        }

        function toggleCustomDates() {
            var isCustom = rangeFilter && rangeFilter.value === 'custom';
            document.querySelectorAll('.report-custom-date').forEach(function (element) {
                element.classList.toggle('d-none', !isCustom);
            });
            updateExportLink();
        }

        function themeColor(name, fallback) {
            var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return value || fallback;
        }

        function setupCanvas(canvas) {
            if (!canvas || !canvas.parentElement) return null;
            var ratio = window.devicePixelRatio || 1;
            var width = Math.max(1, Math.floor(canvas.parentElement.clientWidth));
            var height = Math.max(1, Math.floor(canvas.parentElement.clientHeight));
            canvas.width = Math.floor(width * ratio);
            canvas.height = Math.floor(height * ratio);
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            var ctx = canvas.getContext('2d');
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            return { ctx: ctx, width: width, height: height };
        }

        function drawTrend() {
            var setup = setupCanvas(document.getElementById('reportsLineChart'));
            if (!setup) return;
            var ctx = setup.ctx, w = setup.width, h = setup.height;
            var trend = reportPayload.trend || {};
            var labels = Array.isArray(trend.labels) ? trend.labels : [];
            var donors = Array.isArray(trend.donors) ? trend.donors : [];
            var donations = Array.isArray(trend.donations) ? trend.donations : [];
            var maxValue = Math.max(1, ...donors, ...donations);
            var left = 42, right = 12, top = 14, bottom = 32;
            var chartW = Math.max(1, w - left - right), chartH = Math.max(1, h - top - bottom);
            ctx.clearRect(0, 0, w, h);
            ctx.strokeStyle = themeColor('--bs-border-color', '#e2e8f0');
            ctx.fillStyle = themeColor('--bs-secondary-color', '#64748b');
            ctx.font = '11px Poppins, sans-serif';
            ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
            for (var tick = 0; tick <= 4; tick += 1) {
                var y = top + chartH - (tick / 4) * chartH;
                ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(left + chartW, y); ctx.stroke();
                ctx.fillText(String(Math.round((maxValue / 4) * tick)), left - 6, y);
            }
            ctx.textAlign = 'center'; ctx.textBaseline = 'top';
            labels.forEach(function (label, index) {
                var x = left + (chartW / Math.max(1, labels.length - 1)) * index;
                ctx.fillText(label, x, h - bottom + 8);
            });
            function series(values, color) {
                if (!labels.length) return;
                var points = values.map(function (value, index) {
                    return { x: left + (chartW / Math.max(1, labels.length - 1)) * index, y: top + chartH - ((Number(value) || 0) / maxValue) * chartH };
                });
                ctx.beginPath(); ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.moveTo(points[0].x, points[0].y);
                points.slice(1).forEach(function (point) { ctx.lineTo(point.x, point.y); }); ctx.stroke();
                ctx.fillStyle = color; points.forEach(function (point) { ctx.beginPath(); ctx.arc(point.x, point.y, 2.5, 0, Math.PI * 2); ctx.fill(); });
            }
            series(donors, '#b60c0c');
            series(donations, '#f28b8b');
        }

        function drawDistribution() {
            var setup = setupCanvas(document.getElementById('reportsBarChart'));
            if (!setup) return;
            var ctx = setup.ctx, w = setup.width, h = setup.height;
            var items = reportPayload.distribution && Array.isArray(reportPayload.distribution.items) ? reportPayload.distribution.items : [];
            var labels = items.map(function (item) { return item.label; });
            var values = items.map(function (item) { return Number(item.count) || 0; });
            var maxValue = Math.max(1, ...values);
            var left = 36, right = 12, top = 14, bottom = 34;
            var chartW = Math.max(1, w - left - right), chartH = Math.max(1, h - top - bottom);
            ctx.clearRect(0, 0, w, h); ctx.strokeStyle = themeColor('--bs-border-color', '#e2e8f0'); ctx.fillStyle = themeColor('--bs-secondary-color', '#64748b'); ctx.font = '11px Poppins, sans-serif'; ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
            for (var tick = 0; tick <= 4; tick += 1) { var y = top + chartH - (tick / 4) * chartH; ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(left + chartW, y); ctx.stroke(); ctx.fillText(String(Math.round((maxValue / 4) * tick)), left - 6, y); }
            var slot = chartW / Math.max(1, values.length), width = slot * 0.56;
            labels.forEach(function (label, index) { var height = (values[index] / maxValue) * chartH; var x = left + slot * index + (slot - width) / 2; ctx.fillStyle = ['#b60c0c', '#850000', '#e83333', '#f07070', '#cc2f2f', '#ff9a9a'][index % 6]; ctx.fillRect(x, top + chartH - height, width, height); ctx.fillStyle = themeColor('--bs-body-color', '#334155'); ctx.textAlign = 'center'; ctx.textBaseline = 'top'; ctx.fillText(label, x + width / 2, h - bottom + 8); });
        }

        function renderInventory() {
            if (!inventoryBody) return;
            var rows = Array.isArray(reportPayload.inventory) ? reportPayload.inventory : [];
            if (!rows.length) { inventoryBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No inventory data available.</td></tr>'; return; }
            inventoryBody.innerHTML = rows.map(function (row) {
                var status = String(row.status || 'available');
                var statusClass = status === 'out_of_stock' ? 'report-demand-badge--critical' : (status === 'low' ? 'report-demand-badge--high' : 'report-demand-badge--low');
                return '<tr><td><strong>' + escapeHtml(row.blood_type) + '</strong></td><td>' + number(row.available_units) + '</td><td>' + number(row.reserved_units) + '</td><td>' + number(row.open_request_demand) + '</td><td><span class="report-demand-badge ' + statusClass + '">' + escapeHtml(row.status_label) + '</span></td></tr>';
            }).join('');
        }

        function render() {
            var summary = reportPayload.summary || {};
            document.querySelectorAll('[data-report-metric]').forEach(function (element) {
                var key = element.getAttribute('data-report-metric');
                element.textContent = number(summary[key], key === 'success_rate' ? 1 : 0) + (key === 'success_rate' ? '%' : '');
            });
            var period = reportPayload.period || {};
            if (periodNote) periodNote.textContent = 'Showing aggregate data for ' + (period.label || 'the selected period') + '.';
            var distribution = reportPayload.distribution || {};
            if (distributionCaption) distributionCaption.textContent = 'Verified records: ' + number(distribution.verified_total) + '. Self-reported: ' + number(distribution.self_reported_total) + '. Unknown: ' + number(distribution.unknown_total) + '.';
            renderInventory(); drawTrend(); drawDistribution(); updateExportLink();
        }

        function fetchReport() {
            if (!dataUrl) return;
            var query = queryParams().toString();
            document.body.classList.add('report-loading');
            fetch(dataUrl + (query ? '?' + query : ''), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (response) { return response.json().then(function (body) { if (!response.ok) throw new Error(body.message || 'Unable to load report data.'); return body; }); })
                .then(function (body) { reportPayload = body; render(); })
                .catch(function (error) { if (periodNote) periodNote.textContent = error.message || 'Unable to load report data.'; })
                .finally(function () { document.body.classList.remove('report-loading'); });
        }

        if (rangeFilter) rangeFilter.addEventListener('change', toggleCustomDates);
        [startDate, endDate, facilityFilter, bloodTypeFilter].forEach(function (element) { if (element) element.addEventListener('change', updateExportLink); });
        if (applyButton) applyButton.addEventListener('click', fetchReport);
        if (resetButton) resetButton.addEventListener('click', function () { rangeFilter.value = 'year'; startDate.value = ''; endDate.value = ''; facilityFilter.value = ''; bloodTypeFilter.value = ''; toggleCustomDates(); fetchReport(); });
        window.addEventListener('resize', function () { if (resizeTimer) window.clearTimeout(resizeTimer); resizeTimer = window.setTimeout(function () { drawTrend(); drawDistribution(); }, 120); });
        window.addEventListener('edonate:themechange', function () { drawTrend(); drawDistribution(); });
        toggleCustomDates(); render();
    })();
</script>
@endpush
