@extends('layouts.admin')

@section('title', 'eDonate - Admin Dashboard')
@section('admin_page_class', 'admin-dashboard-page')
@section('header_title', 'Admin Dashboard')
@section('header_subtitle', 'Blood Donation Management System - Web Portal')

@section('header_actions')
	<div class="header__date-group" aria-label="Current date">
		<p class="header__date-label">Today's Date</p>
		<p class="header__date-value" id="todayDate">-</p>
	</div>

	<div class="header__icon-actions" aria-label="Dashboard actions">
		<a class="header__icon-btn" href="{{ route('admin.settings') }}" aria-label="Go to Settings">
			<i class="bi bi-gear-fill" aria-hidden="true"></i>
		</a>
	</div>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'admin-dashboard',
	'dashboard' => [
		'monthlyDonations' => data_get($dashboardPayload ?? [], 'monthly_donations', [
			'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
			'values' => array_fill(0, 12, 0),
			'maxY' => 5,
			'stepY' => 1,
		]),
		'bloodTypeDistribution' => data_get($dashboardPayload ?? [], 'blood_type_distribution', []),
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	@php
		$dashboard = $dashboardPayload ?? [];
		$stats = data_get($dashboard, 'stats', []);
		$notificationBanner = data_get($dashboard, 'notification_banner');
		$recentActivities = data_get($dashboard, 'recent_activities', []);
		$pendingApprovals = data_get($dashboard, 'pending_approvals', []);
		$dashboardLinks = data_get($dashboard, 'links', []);
	@endphp
	<div class="main container-fluid px-0">
		<main class="content container-fluid py-3">
			@if ($notificationBanner)
				<x-admin-notification-banner :notification="$notificationBanner" />
			@endif
			<section class="dashboard-stats row" aria-label="Dashboard statistics">
				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--red h-100">
						<div class="stat-card__icons">
							<span class="stat-card__icon-main" aria-hidden="true">&#128101;</span>
							<span class="stat-card__icon-aux" aria-hidden="true">&#8599;</span>
						</div>
						<p class="stat-card__label stat-card__label--white">Total Donors</p>
						<p class="stat-card__value stat-card__value--white">{{ data_get($stats, 'total_donors.value', '0') }}</p>
						<p class="stat-card__change stat-card__change--white">{{ data_get($stats, 'total_donors.change', '0% this month') }}</p>
					</div>
				</div>

				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--green h-100">
						<div class="stat-card__icons">
							<span class="stat-card__icon-main" aria-hidden="true">&#10004;</span>
							<span class="stat-card__icon-aux" aria-hidden="true">&#128202;</span>
						</div>
						<p class="stat-card__label">Successful Donations</p>
						<p class="stat-card__value">{{ data_get($stats, 'successful_donations.value', '0') }}</p>
						<p class="stat-card__change stat-card__change--green">{{ data_get($stats, 'successful_donations.change', '0% this month') }}</p>
					</div>
				</div>

				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--blue h-100">
						<div class="stat-card__icons">
							<span class="stat-card__icon-main" aria-hidden="true">&#128197;</span>
							<span class="stat-card__icon-aux" aria-hidden="true">&#128339;</span>
						</div>
						<p class="stat-card__label">Upcoming Appointments</p>
						<p class="stat-card__value">{{ data_get($stats, 'upcoming_appointments.value', '0') }}</p>
						<p class="stat-card__change stat-card__change--blue">{{ data_get($stats, 'upcoming_appointments.change', '0% this month') }}</p>
					</div>
				</div>

				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--gold h-100">
						<div class="stat-card__icons">
							<span class="stat-card__icon-main" aria-hidden="true">&#129656;</span>
							<span class="stat-card__icon-aux" aria-hidden="true">&#128204;</span>
						</div>
						<p class="stat-card__label">Donation Records</p>
						<p class="stat-card__value">{{ data_get($stats, 'donation_records.value', '0') }}</p>
						<p class="stat-card__change stat-card__change--gold">{{ data_get($stats, 'donation_records.change', '0% this month') }}</p>
					</div>
				</div>
			</section>

			@php($operational = data_get($stats, 'operational', []))
			<section class="dashboard-operations row g-3" aria-label="Operational KPIs">
				@foreach ([
					['key' => 'verified_donors', 'label' => 'Verified donors'],
					['key' => 'pending_verification', 'label' => 'Pending verification'],
					['key' => 'eligible_donors', 'label' => 'Eligible now'],
					['key' => 'open_requests', 'label' => 'Open requests'],
					['key' => 'emergency_requests', 'label' => 'Emergency requests'],
					['key' => 'low_stock', 'label' => 'Low stock types'],
					['key' => 'out_of_stock', 'label' => 'Out of stock'],
				] as $kpi)
					<div class="col-6 col-md-3 col-xl-2">
						<article class="dashboard-kpi-card h-100">
							<span class="dashboard-kpi-card__label">{{ $kpi['label'] }}</span>
							<strong class="dashboard-kpi-card__value">{{ number_format((int) data_get($operational, $kpi['key'], 0)) }}</strong>
						</article>
					</div>
				@endforeach
			</section>

			<section class="map-banner d-flex align-items-center" aria-label="Geographic blood availability map">
				<div class="map-banner__pin" aria-hidden="true">
					<svg width="22" height="28" viewBox="0 0 22 28" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M11 0C4.925 0 0 4.925 0 11c0 8.25 11 17 11 17s11-8.75 11-17C22 4.925 17.075 0 11 0zm0 14.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z" fill="white"/>
					</svg>
				</div>
				<div class="map-banner__text">
					<p class="map-banner__title">Geographic Blood Availability Map</p>
					<p class="map-banner__subtitle">Monitor blood availability across all locations in real-time</p>
				</div>
				<a class="map-banner__btn btn" href="{{ data_get($dashboardLinks, 'map', route('admin.blood-availability-mapping')) }}">View Map</a>
			</section>

			<section class="dashboard-charts row" aria-label="Data visualization charts">
				<div class="col-12 col-lg-6">
					<div class="chart-panel h-100">
						<h2 class="chart-panel__title">Monthly Donations Trend</h2>
						<div class="chart-panel__body">
							<canvas id="lineChart" aria-label="Monthly donations trend line chart"></canvas>
						</div>
					</div>
				</div>
				<div class="col-12 col-lg-6">
					<div class="chart-panel h-100">
						<h2 class="chart-panel__title">Blood Type Distribution</h2>
						<div class="chart-panel__body">
							<canvas id="pieChart" aria-label="Blood type distribution pie chart"></canvas>
						</div>
					</div>
				</div>
			</section>

			<section class="dashboard-bottom row" aria-label="Recent activities and pending approvals">
				<div class="col-12 col-lg-6">
					<div class="panel h-100">
					<h2 class="panel__title">Recent Activities</h2>
					<ul class="activity-list">
						@forelse ($recentActivities as $activity)
							<li class="activity-item">
								<span class="activity-item__dot activity-item__dot--{{ data_get($activity, 'tone', 'blue') }}"></span>
								<div class="activity-item__info">
									<p class="activity-item__name">{{ data_get($activity, 'name', 'System') }}</p>
									<p class="activity-item__action">{{ data_get($activity, 'action', 'Activity') }}</p>
								</div>
								<span class="activity-item__time">{{ data_get($activity, 'time', 'Recently') }}</span>
							</li>
						@empty
							<li class="activity-item">
								<span class="activity-item__dot activity-item__dot--blue"></span>
								<div class="activity-item__info">
									<p class="activity-item__name">No recent activities yet.</p>
									<p class="activity-item__action">Activity will appear here once records are created.</p>
								</div>
								<span class="activity-item__time">-</span>
							</li>
						@endforelse
					</ul>
					<a class="panel__footer-btn panel__footer-btn--red btn" href="{{ data_get($dashboardLinks, 'activities', route('admin.audit-logs')) }}">View All Activities</a>
					</div>
				</div>

				<div class="col-12 col-lg-6">
					<div class="panel h-100">
					<h2 class="panel__title">Pending Approvals</h2>
					<ul class="approval-list">
						@forelse ($pendingApprovals as $approval)
							<li class="approval-item">
								<div class="approval-item__info">
									<p class="approval-item__name">{{ data_get($approval, 'name', 'Unknown donor') }}</p>
									<p class="approval-item__type">{{ data_get($approval, 'type', 'Pending approval') }}</p>
								</div>
								<div class="approval-item__actions">
									<a class="btn-approve btn" href="{{ data_get($approval, 'approve_url', route('admin.eligibility.index')) }}">Approve</a>
									<a class="btn-review btn" href="{{ data_get($approval, 'review_url', route('admin.eligibility.index')) }}">Review</a>
								</div>
							</li>
						@empty
							<li class="approval-item">
								<div class="approval-item__info">
									<p class="approval-item__name">No pending approvals at the moment.</p>
									<p class="approval-item__type">Pending eligibility and appointment items will appear here.</p>
								</div>
							</li>
						@endforelse
					</ul>
					<a class="panel__footer-btn panel__footer-btn--green btn" href="{{ data_get($dashboardLinks, 'approvals', route('admin.eligibility.index')) }}">View All Approvals</a>
					</div>
				</div>
			</section>
		</main>
	</div>
@endsection

@push('admin_scripts')
<script>
	(function () {
		var dateElement = document.getElementById('todayDate');
		if (dateElement) {
			var now = new Date();
			dateElement.textContent = now.toLocaleDateString('en-US', {
				year: 'numeric',
				month: 'long',
				day: 'numeric'
			});
		}

		var dashboardData = (window.AdminPageData && window.AdminPageData.dashboard) ? window.AdminPageData.dashboard : {};

		function themeColor(name, fallback) {
			var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
			return value || fallback;
		}

		function setupCanvas(canvas) {
			var parent = canvas.parentElement;
			var ratio = window.devicePixelRatio || 1;
			var width = parent.clientWidth;
			var height = parent.clientHeight;

			canvas.width = Math.max(1, Math.floor(width * ratio));
			canvas.height = Math.max(1, Math.floor(height * ratio));
			canvas.style.width = width + 'px';
			canvas.style.height = height + 'px';

			var ctx = canvas.getContext('2d');
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

			return { ctx: ctx, width: width, height: height };
		}

		function drawSmoothLine(ctx, points) {
			if (points.length < 2) {
				return;
			}

			ctx.beginPath();
			ctx.moveTo(points[0].x, points[0].y);

			for (var i = 1; i < points.length - 1; i += 1) {
				var midX = (points[i].x + points[i + 1].x) / 2;
				var midY = (points[i].y + points[i + 1].y) / 2;
				ctx.quadraticCurveTo(points[i].x, points[i].y, midX, midY);
			}

			var last = points.length - 1;
			ctx.quadraticCurveTo(points[last - 1].x, points[last - 1].y, points[last].x, points[last].y);
		}

		function drawLineChart() {
			var canvas = document.getElementById('lineChart');
			if (!canvas) {
				return;
			}

			var setup = setupCanvas(canvas);
			var ctx = setup.ctx;
			var w = setup.width;
			var h = setup.height;

			var mL = 42;
			var mR = 18;
			var mT = 16;
			var mB = 40;
			var plotW = w - mL - mR;
			var plotH = h - mT - mB;

			var lineChartData = (dashboardData && dashboardData.monthlyDonations) ? dashboardData.monthlyDonations : {};
			var months = Array.isArray(lineChartData.labels) && lineChartData.labels.length
				? lineChartData.labels
				: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
			var values = Array.isArray(lineChartData.values) && lineChartData.values.length
				? lineChartData.values
				: months.map(function () { return 0; });
			var yMax = Number(lineChartData.maxY || 5);
			var yStep = Number(lineChartData.stepY || 1);

			ctx.clearRect(0, 0, w, h);
			ctx.font = '11px Poppins, sans-serif';
			ctx.fillStyle = themeColor('--bs-secondary-color', '#666');
			ctx.textAlign = 'right';
			ctx.textBaseline = 'middle';
			ctx.strokeStyle = themeColor('--bs-border-color', '#e8e8e8');
			ctx.lineWidth = 1;

			for (var y = 0; y <= yMax; y += yStep) {
				var yPos = mT + plotH - (y / yMax) * plotH;
				ctx.beginPath();
				ctx.moveTo(mL, yPos);
				ctx.lineTo(mL + plotW, yPos);
				ctx.stroke();
				ctx.fillText(String(y), mL - 6, yPos);
			}

			ctx.textAlign = 'center';
			ctx.textBaseline = 'top';
			ctx.fillStyle = themeColor('--bs-body-color', '#333');

			for (var i = 0; i < months.length; i += 1) {
				var x = mL + (i / (months.length - 1)) * plotW;
				ctx.fillText(months[i], x, h - mB + 8);
			}

			var points = [];
			for (var j = 0; j < values.length; j += 1) {
				points.push({
					x: mL + (j / (values.length - 1)) * plotW,
					y: mT + plotH - (values[j] / yMax) * plotH
				});
			}

			var grad = ctx.createLinearGradient(0, mT, 0, mT + plotH);
			grad.addColorStop(0, 'rgba(182, 12, 12, 0.25)');
			grad.addColorStop(1, 'rgba(182, 12, 12, 0.00)');

			ctx.beginPath();
			drawSmoothLine(ctx, points);
			ctx.lineTo(points[points.length - 1].x, mT + plotH);
			ctx.lineTo(points[0].x, mT + plotH);
			ctx.closePath();
			ctx.fillStyle = grad;
			ctx.fill();

			ctx.strokeStyle = '#b60c0c';
			ctx.lineWidth = 2.5;
			ctx.lineJoin = 'round';
			ctx.lineCap = 'round';
			drawSmoothLine(ctx, points);
			ctx.stroke();

			for (var k = 0; k < points.length; k += 1) {
				ctx.beginPath();
				ctx.arc(points[k].x, points[k].y, 4, 0, Math.PI * 2);
				ctx.fillStyle = '#b60c0c';
				ctx.strokeStyle = themeColor('--bs-body-bg', '#fff');
				ctx.lineWidth = 2;
				ctx.fill();
				ctx.stroke();
			}

			ctx.fillStyle = '#b60c0c';
			ctx.fillRect(w - 120, 14, 12, 12);
			ctx.fillStyle = themeColor('--bs-body-color', '#333');
			ctx.font = '11px Poppins, sans-serif';
			ctx.textAlign = 'left';
			ctx.textBaseline = 'middle';
			ctx.fillText('Donations', w - 102, 20);
		}

		function drawPieChart() {
			var canvas = document.getElementById('pieChart');
			if (!canvas) {
				return;
			}

			var setup = setupCanvas(canvas);
			var ctx = setup.ctx;
			var w = setup.width;
			var h = setup.height;

			var segmentSource = Array.isArray(dashboardData.bloodTypeDistribution)
				? dashboardData.bloodTypeDistribution
				: [];

			var segments = segmentSource.map(function (item) {
				return {
					label: String(item.label || ''),
					value: Number(item.value || 0),
					color: item.color || '#b60c0c'
				};
			}).filter(function (item) {
				return item.label && item.value > 0;
			});

			var legendW = 112;
			var chartW = w - legendW;
			var cx = chartW / 2;
			var cy = h / 2;
			var outerR = Math.min(chartW, h) / 2 - 18;
			var innerR = outerR * 0.45;

			ctx.clearRect(0, 0, w, h);

			if (!segments.length) {
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.font = '600 13px Poppins, sans-serif';
				ctx.fillStyle = themeColor('--bs-secondary-color', '#666');
				ctx.fillText('No donor blood type data yet', w / 2, h / 2);
				return;
			}

			var start = -Math.PI / 2;
			for (var i = 0; i < segments.length; i += 1) {
				var seg = segments[i];
				var end = start + seg.value * Math.PI * 2;
				ctx.beginPath();
				ctx.moveTo(cx, cy);
				ctx.arc(cx, cy, outerR, start, end);
				ctx.closePath();
				ctx.fillStyle = seg.color;
				ctx.fill();
				ctx.strokeStyle = themeColor('--bs-body-bg', '#fff');
				ctx.lineWidth = 2;
				ctx.stroke();
				start = end;
			}

			ctx.beginPath();
			ctx.arc(cx, cy, innerR, 0, Math.PI * 2);
			ctx.fillStyle = themeColor('--bs-body-bg', '#fff');
			ctx.fill();

			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.font = '700 13px Poppins, sans-serif';
			ctx.fillStyle = themeColor('--bs-body-color', '#333');
			ctx.fillText('Blood', cx, cy - 8);
			ctx.fillText('Types', cx, cy + 8);

			var lx = chartW + 10;
			var ly = (h - segments.length * 22) / 2;
			ctx.textAlign = 'left';
			ctx.textBaseline = 'middle';
			ctx.font = '11px Poppins, sans-serif';

			for (var j = 0; j < segments.length; j += 1) {
				var legend = segments[j];
				ctx.fillStyle = legend.color;
				ctx.fillRect(lx, ly - 6, 12, 12);
				ctx.fillStyle = themeColor('--bs-body-color', '#333');
				ctx.fillText(legend.label + '  ' + Math.round(legend.value * 100) + '%', lx + 16, ly);
				ly += 22;
			}
		}

		var resizeTimer;
		function renderCharts() {
			drawLineChart();
			drawPieChart();
		}

		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(renderCharts, 120);
		});

		window.addEventListener('edonate:themechange', renderCharts);

		renderCharts();
	})();
</script>
@endpush
