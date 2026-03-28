@extends('layouts.admin')

@section('title', 'eDonate - Reports & Analytics')
@section('admin_page_class', 'admin-report-analytics-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')

@section('header_title', 'Reports & Analytics')
@section('header_subtitle', 'Visual charts summarizing donation trends and user activity')

@section('header_actions')
	<button class="report-header-export btn" type="button" aria-label="Export all reports">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M12 3V14" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
			<path d="M8 7L12 3L16 7" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
			<path d="M4 17V20C4 20.5523 4.44772 21 5 21H19C19.5523 21 20 20.5523 20 20V17" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
		</svg>
		Export All Reports
	</button>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'report-analytics',
	'reportAnalytics' => [
		'trend' => [
			'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
			'donors' => [182, 194, 207, 228, 241, 256, 278, 291, 305, 324, 338, 352],
			'donations' => [210, 225, 244, 259, 281, 297, 319, 334, 352, 370, 386, 401],
		],
		'distribution' => [
			['label' => 'O+', 'value' => 425],
			['label' => 'A+', 'value' => 340],
			['label' => 'B+', 'value' => 280],
			['label' => 'AB+', 'value' => 195],
			['label' => 'A-', 'value' => 156],
			['label' => 'O-', 'value' => 98],
			['label' => 'B-', 'value' => 134],
			['label' => 'AB-', 'value' => 87],
		],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	<main class="main container-fluid px-0">
		<div class="page-body container-fluid py-3">
			<section class="report-filter row g-3 align-items-center" role="region" aria-label="Report filters">
				<div class="col-12 col-md-6 col-xl-3">
					<div class="report-filter__control">
						<span class="report-filter__icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<rect x="3" y="4" width="18" height="17" rx="2" stroke="#444" stroke-width="1.5"/>
								<path d="M8 2V6M16 2V6M3 9H21" stroke="#444" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
						</span>
						<select class="report-filter__select form-select" aria-label="Filter by date range">
							<option selected>Last 30 Days</option>
							<option>Last 7 Days</option>
							<option>Last 90 Days</option>
							<option>This Year</option>
						</select>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-3">
					<div class="report-filter__control">
						<span class="report-filter__icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2Z" fill="#555"/>
								<circle cx="12" cy="9" r="2.2" fill="white"/>
							</svg>
						</span>
						<select class="report-filter__select form-select" aria-label="Filter by location">
							<option selected>All Locations</option>
							<option>Lipa Medix</option>
							<option>Mary Mediatrix</option>
							<option>Ospital ng Lipa</option>
						</select>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-3">
					<div class="report-filter__control">
						<span class="report-filter__icon" aria-hidden="true">
							<svg width="14" height="18" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M5 0C5 0 0 6.5 0 10A5 5 0 0 0 10 10C10 6.5 5 0 5 0Z" fill="#b60c0c"/>
							</svg>
						</span>
						<select class="report-filter__select form-select" aria-label="Filter by blood type">
							<option selected>All Blood Types</option>
							<option>A+</option>
							<option>A-</option>
							<option>B+</option>
							<option>B-</option>
							<option>AB+</option>
							<option>AB-</option>
							<option>O+</option>
							<option>O-</option>
						</select>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-3">
					<button class="report-filter__export btn" type="button" aria-label="Export filtered report">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<path d="M12 3V14" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M8 7L12 3L16 7" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M4 17V20C4 20.5523 4.44772 21 5 21H19C19.5523 21 20 20.5523 20 20V17" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
						</svg>
						Export Reports
					</button>
				</div>
			</section>

			<section class="report-stats row g-3" aria-label="Report summary metrics">
				<div class="col-12 col-sm-6 col-xl-3">
					<article class="report-stat-card report-stat-card--red h-100">
						<span class="report-stat-card__label">Total Donations</span>
						<span class="report-stat-card__value">2,956</span>
						<span class="report-stat-card__note report-stat-card__note--green">+15.3% vs last period</span>
					</article>
				</div>

				<div class="col-12 col-sm-6 col-xl-3">
					<article class="report-stat-card report-stat-card--green h-100">
						<span class="report-stat-card__label">Active Donors</span>
						<span class="report-stat-card__value">1,847</span>
						<span class="report-stat-card__note report-stat-card__note--green">+8.7% vs last period</span>
					</article>
				</div>

				<div class="col-12 col-sm-6 col-xl-3">
					<article class="report-stat-card report-stat-card--blue h-100">
						<span class="report-stat-card__label">Avg. per Month</span>
						<span class="report-stat-card__value">369</span>
						<span class="report-stat-card__note report-stat-card__note--blue">Steady growth</span>
					</article>
				</div>

				<div class="col-12 col-sm-6 col-xl-3">
					<article class="report-stat-card report-stat-card--gold h-100">
						<span class="report-stat-card__label">Success Rate</span>
						<span class="report-stat-card__value">94.6%</span>
						<span class="report-stat-card__note report-stat-card__note--green">+2.1% vs last period</span>
					</article>
				</div>
			</section>

			<section class="report-charts row g-3" aria-label="Report charts">
				<div class="col-12 col-xl-6">
					<article class="report-chart-card h-100">
						<h2 class="report-chart-card__title">Monthly Donations Trend</h2>
						<div class="report-chart-card__canvas-wrap">
							<canvas id="reportsLineChart" aria-label="Monthly donations trend chart"></canvas>
						</div>
						<div class="report-chart-card__legend" aria-label="Trend chart legend">
							<span class="report-chart-card__legend-item">
								<span class="report-chart-card__dot report-chart-card__dot--red" aria-hidden="true"></span>
								Donors
							</span>
							<span class="report-chart-card__legend-item">
								<span class="report-chart-card__dot report-chart-card__dot--pink" aria-hidden="true"></span>
								Donations
							</span>
						</div>
					</article>
				</div>

				<div class="col-12 col-xl-6">
					<article class="report-chart-card h-100">
						<h2 class="report-chart-card__title">Blood Type Distribution</h2>
						<div class="report-chart-card__canvas-wrap">
							<canvas id="reportsBarChart" aria-label="Blood type distribution chart"></canvas>
						</div>
					</article>
				</div>
			</section>

			<section class="report-inventory-card" aria-label="Blood type inventory and demand">
				<h2 class="report-inventory-card__title">Blood Type Inventory &amp; Demand</h2>

				<div class="table-responsive">
					<table class="table report-inventory-table align-middle mb-0">
						<thead>
							<tr>
								<th scope="col">Blood Type</th>
								<th scope="col">Quantity (Units)</th>
								<th scope="col" class="col-demand">Demand Level</th>
								<th scope="col">Status</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>
									<div class="report-inventory-table__blood-cell">
										<span class="report-inventory-table__blood-icon" aria-hidden="true">
											<svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M5 0C5 0 0 6.5 0 10A5 5 0 0010 10C10 6.5 5 0 5 0Z" fill="#b60c0c"/>
											</svg>
										</span>
										A+
									</div>
								</td>
								<td>340</td>
								<td class="col-demand"><span class="report-demand-badge report-demand-badge--high">High</span></td>
								<td>
									<div class="progress report-status-progress" role="progressbar" aria-label="A+ stock status" aria-valuenow="87" aria-valuemin="0" aria-valuemax="100">
										<div class="progress-bar report-status-progress__bar report-status-progress__bar--green" style="width: 87%"></div>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="report-inventory-table__blood-cell">
										<span class="report-inventory-table__blood-icon" aria-hidden="true">
											<svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M5 0C5 0 0 6.5 0 10A5 5 0 0010 10C10 6.5 5 0 5 0Z" fill="#b60c0c"/>
											</svg>
										</span>
										O+
									</div>
								</td>
								<td>425</td>
								<td class="col-demand"><span class="report-demand-badge report-demand-badge--critical">Critical</span></td>
								<td>
									<div class="progress report-status-progress" role="progressbar" aria-label="O+ stock status" aria-valuenow="94" aria-valuemin="0" aria-valuemax="100">
										<div class="progress-bar report-status-progress__bar report-status-progress__bar--green" style="width: 94%"></div>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="report-inventory-table__blood-cell">
										<span class="report-inventory-table__blood-icon" aria-hidden="true">
											<svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M5 0C5 0 0 6.5 0 10A5 5 0 0010 10C10 6.5 5 0 5 0Z" fill="#b60c0c"/>
											</svg>
										</span>
										B+
									</div>
								</td>
								<td>280</td>
								<td class="col-demand"><span class="report-demand-badge report-demand-badge--medium">Medium</span></td>
								<td>
									<div class="progress report-status-progress" role="progressbar" aria-label="B+ stock status" aria-valuenow="69" aria-valuemin="0" aria-valuemax="100">
										<div class="progress-bar report-status-progress__bar report-status-progress__bar--yellow" style="width: 69%"></div>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="report-inventory-table__blood-cell">
										<span class="report-inventory-table__blood-icon" aria-hidden="true">
											<svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M5 0C5 0 0 6.5 0 10A5 5 0 0010 10C10 6.5 5 0 5 0Z" fill="#b60c0c"/>
											</svg>
										</span>
										AB+
									</div>
								</td>
								<td>195</td>
								<td class="col-demand"><span class="report-demand-badge report-demand-badge--low">Low</span></td>
								<td>
									<div class="progress report-status-progress" role="progressbar" aria-label="AB+ stock status" aria-valuenow="49" aria-valuemin="0" aria-valuemax="100">
										<div class="progress-bar report-status-progress__bar report-status-progress__bar--orange" style="width: 49%"></div>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="report-inventory-table__blood-cell">
										<span class="report-inventory-table__blood-icon" aria-hidden="true">
											<svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M5 0C5 0 0 6.5 0 10A5 5 0 0010 10C10 6.5 5 0 5 0Z" fill="#b60c0c"/>
											</svg>
										</span>
										A-
									</div>
								</td>
								<td>156</td>
								<td class="col-demand"><span class="report-demand-badge report-demand-badge--high">High</span></td>
								<td>
									<div class="progress report-status-progress" role="progressbar" aria-label="A- stock status" aria-valuenow="12" aria-valuemin="0" aria-valuemax="100">
										<div class="progress-bar report-status-progress__bar report-status-progress__bar--red" style="width: 12%"></div>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="report-inventory-table__blood-cell">
										<span class="report-inventory-table__blood-icon" aria-hidden="true">
											<svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M5 0C5 0 0 6.5 0 10A5 5 0 0010 10C10 6.5 5 0 5 0Z" fill="#b60c0c"/>
											</svg>
										</span>
										O-
									</div>
								</td>
								<td>98</td>
								<td class="col-demand"><span class="report-demand-badge report-demand-badge--critical">Critical</span></td>
								<td>
									<div class="progress report-status-progress" role="progressbar" aria-label="O- stock status" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100">
										<div class="progress-bar report-status-progress__bar report-status-progress__bar--orange" style="width: 35%"></div>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="report-inventory-table__blood-cell">
										<span class="report-inventory-table__blood-icon" aria-hidden="true">
											<svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M5 0C5 0 0 6.5 0 10A5 5 0 0010 10C10 6.5 5 0 5 0Z" fill="#b60c0c"/>
											</svg>
										</span>
										B-
									</div>
								</td>
								<td>134</td>
								<td class="col-demand"><span class="report-demand-badge report-demand-badge--medium">Medium</span></td>
								<td>
									<div class="progress report-status-progress" role="progressbar" aria-label="B- stock status" aria-valuenow="28" aria-valuemin="0" aria-valuemax="100">
										<div class="progress-bar report-status-progress__bar report-status-progress__bar--orange" style="width: 28%"></div>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div class="report-inventory-table__blood-cell">
										<span class="report-inventory-table__blood-icon" aria-hidden="true">
											<svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M5 0C5 0 0 6.5 0 10A5 5 0 0010 10C10 6.5 5 0 5 0Z" fill="#b60c0c"/>
											</svg>
										</span>
										AB-
									</div>
								</td>
								<td>87</td>
								<td class="col-demand"><span class="report-demand-badge report-demand-badge--low">Low</span></td>
								<td>
									<div class="progress report-status-progress" role="progressbar" aria-label="AB- stock status" aria-valuenow="38" aria-valuemin="0" aria-valuemax="100">
										<div class="progress-bar report-status-progress__bar report-status-progress__bar--orange" style="width: 38%"></div>
									</div>
								</td>
							</tr>
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
		var reportData = (window.AdminPageData && window.AdminPageData.reportAnalytics) ? window.AdminPageData.reportAnalytics : {};
		var resizeTimer = null;

		function setupCanvas(canvas) {
			var parent = canvas.parentElement;
			if (!parent) {
				return null;
			}

			var ratio = window.devicePixelRatio || 1;
			var width = Math.max(1, Math.floor(parent.clientWidth));
			var height = Math.max(1, Math.floor(parent.clientHeight));

			canvas.width = Math.floor(width * ratio);
			canvas.height = Math.floor(height * ratio);
			canvas.style.width = width + 'px';
			canvas.style.height = height + 'px';

			var ctx = canvas.getContext('2d');
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

			return { ctx: ctx, width: width, height: height };
		}

		function drawLineSeries(ctx, points, color) {
			if (points.length < 2) {
				return;
			}

			ctx.beginPath();
			ctx.strokeStyle = color;
			ctx.lineWidth = 2;
			ctx.moveTo(points[0].x, points[0].y);

			for (var i = 1; i < points.length; i += 1) {
				ctx.lineTo(points[i].x, points[i].y);
			}

			ctx.stroke();

			ctx.fillStyle = color;
			for (var j = 0; j < points.length; j += 1) {
				ctx.beginPath();
				ctx.arc(points[j].x, points[j].y, 2.4, 0, Math.PI * 2);
				ctx.fill();
			}
		}

		function drawTrendChart() {
			var canvas = document.getElementById('reportsLineChart');
			if (!canvas) {
				return;
			}

			var setup = setupCanvas(canvas);
			if (!setup) {
				return;
			}

			var ctx = setup.ctx;
			var w = setup.width;
			var h = setup.height;

			var trend = reportData && reportData.trend ? reportData.trend : {};
			var labels = Array.isArray(trend.labels) && trend.labels.length ? trend.labels : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
			var donors = Array.isArray(trend.donors) && trend.donors.length ? trend.donors : [120, 150, 180, 200, 230, 260];
			var donations = Array.isArray(trend.donations) && trend.donations.length ? trend.donations : [150, 170, 200, 240, 280, 310];

			var maxValue = 10;
			for (var i = 0; i < donors.length; i += 1) {
				maxValue = Math.max(maxValue, donors[i]);
			}
			for (var j = 0; j < donations.length; j += 1) {
				maxValue = Math.max(maxValue, donations[j]);
			}
			maxValue = Math.ceil(maxValue / 50) * 50;

			var leftPad = 40;
			var rightPad = 12;
			var topPad = 14;
			var bottomPad = 30;
			var chartW = w - leftPad - rightPad;
			var chartH = h - topPad - bottomPad;

			ctx.clearRect(0, 0, w, h);

			ctx.strokeStyle = '#ececec';
			ctx.lineWidth = 1;
			ctx.fillStyle = '#666';
			ctx.font = '11px Poppins, sans-serif';
			ctx.textAlign = 'right';
			ctx.textBaseline = 'middle';

			var yTicks = 5;
			for (var tick = 0; tick <= yTicks; tick += 1) {
				var yValue = (maxValue / yTicks) * tick;
				var y = topPad + chartH - (yValue / maxValue) * chartH;

				ctx.beginPath();
				ctx.moveTo(leftPad, y);
				ctx.lineTo(leftPad + chartW, y);
				ctx.stroke();

				ctx.fillText(String(Math.round(yValue)), leftPad - 6, y);
			}

			ctx.textAlign = 'center';
			ctx.textBaseline = 'top';
			ctx.fillStyle = '#333';

			for (var labelIndex = 0; labelIndex < labels.length; labelIndex += 1) {
				var xPos = leftPad + (chartW / Math.max(1, labels.length - 1)) * labelIndex;
				ctx.fillText(labels[labelIndex], xPos, h - bottomPad + 8);
			}

			var donorPoints = [];
			var donationPoints = [];
			for (var pointIndex = 0; pointIndex < labels.length; pointIndex += 1) {
				var x = leftPad + (chartW / Math.max(1, labels.length - 1)) * pointIndex;
				var donorValue = donors[pointIndex] || 0;
				var donationValue = donations[pointIndex] || 0;

				donorPoints.push({
					x: x,
					y: topPad + chartH - (donorValue / maxValue) * chartH,
				});

				donationPoints.push({
					x: x,
					y: topPad + chartH - (donationValue / maxValue) * chartH,
				});
			}

			drawLineSeries(ctx, donorPoints, '#b60c0c');
			drawLineSeries(ctx, donationPoints, '#f4a0a0');
		}

		function drawDistributionChart() {
			var canvas = document.getElementById('reportsBarChart');
			if (!canvas) {
				return;
			}

			var setup = setupCanvas(canvas);
			if (!setup) {
				return;
			}

			var ctx = setup.ctx;
			var w = setup.width;
			var h = setup.height;

			var distribution = Array.isArray(reportData.distribution) ? reportData.distribution : [];
			if (!distribution.length) {
				distribution = [
					{ label: 'O+', value: 420 },
					{ label: 'A+', value: 330 },
					{ label: 'B+', value: 260 },
					{ label: 'AB+', value: 180 },
				];
			}

			var maxValue = 10;
			for (var i = 0; i < distribution.length; i += 1) {
				maxValue = Math.max(maxValue, Number(distribution[i].value) || 0);
			}
			maxValue = Math.ceil(maxValue / 50) * 50;

			var leftPad = 34;
			var rightPad = 12;
			var topPad = 14;
			var bottomPad = 30;
			var chartW = w - leftPad - rightPad;
			var chartH = h - topPad - bottomPad;
			var barWidth = chartW / Math.max(1, distribution.length) * 0.56;
			var colorPalette = ['#b60c0c', '#850000', '#f13939', '#f78a8a', '#ffb2b2', '#cc2f2f'];

			ctx.clearRect(0, 0, w, h);

			ctx.strokeStyle = '#ececec';
			ctx.lineWidth = 1;
			ctx.fillStyle = '#666';
			ctx.font = '11px Poppins, sans-serif';
			ctx.textAlign = 'right';
			ctx.textBaseline = 'middle';

			var yTicks = 5;
			for (var tick = 0; tick <= yTicks; tick += 1) {
				var yValue = (maxValue / yTicks) * tick;
				var y = topPad + chartH - (yValue / maxValue) * chartH;

				ctx.beginPath();
				ctx.moveTo(leftPad, y);
				ctx.lineTo(leftPad + chartW, y);
				ctx.stroke();

				ctx.fillText(String(Math.round(yValue)), leftPad - 6, y);
			}

			ctx.textAlign = 'center';
			ctx.textBaseline = 'top';

			for (var barIndex = 0; barIndex < distribution.length; barIndex += 1) {
				var item = distribution[barIndex];
				var centerX = leftPad + (chartW / distribution.length) * (barIndex + 0.5);
				var barHeight = ((Number(item.value) || 0) / maxValue) * chartH;
				var barX = centerX - (barWidth / 2);
				var barY = topPad + chartH - barHeight;
				var color = colorPalette[barIndex % colorPalette.length];

				ctx.fillStyle = color;
				ctx.fillRect(barX, barY, barWidth, barHeight);

				ctx.fillStyle = '#333';
				ctx.fillText(item.label, centerX, h - bottomPad + 8);
			}
		}

		function renderCharts() {
			drawTrendChart();
			drawDistributionChart();
		}

		renderCharts();

		window.addEventListener('resize', function () {
			if (resizeTimer) {
				window.clearTimeout(resizeTimer);
			}

			resizeTimer = window.setTimeout(renderCharts, 120);
		});
	})();
</script>
@endpush
