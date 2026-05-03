@extends('layouts.admin')

@section('title', 'eDonate - Admin Dashboard')
@section('admin_page_class', 'admin-dashboard-page')
@section('layout_wrapper_class', 'app')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('render_default_hamburger', 'false')

@section('header_title', 'Admin Dashboardqwerty')
@section('header_subtitle', 'Blood Donation Management System - Web Portal')

@section('header_slot')
	<button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
	</button>
@endsection

@section('header_actions')
	<div class="header__date-group" aria-label="Current date">
		<p class="header__date-label">Today's Date</p>
		<p class="header__date-value" id="todayDate">-</p>
	</div>

	<div class="header__icon-actions" aria-label="Dashboard quick actions">
		<a class="header__icon-btn" href="{{ route('admin.notification-center') }}" aria-label="Go to Notification Center">
			<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
				<path d="M12 2C10.07 2 8.32 2.85 7.14 4.21L3 8.99V15H5V20H19V15H21V8.99L16.86 4.21C15.68 2.85 13.93 2 12 2ZM12 4C13.38 4 14.63 4.57 15.52 5.5H8.48C9.37 4.57 10.62 4 12 4ZM5 10.41L8.14 7H15.86L19 10.41V13H5V10.41ZM7 15H17V18H7V15Z" />
			</svg>
			<span class="header__badge" aria-hidden="true">9+</span>
		</a>

		<a class="header__icon-btn" href="{{ route('admin.settings') }}" aria-label="Go to Settings">
			<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
				<path d="M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5 3.5 3.5 0 0 1-3.5 3.5m7.43-2.92c.04-.34.07-.69.07-1.08s-.03-.74-.07-1.08l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.34-.07.69-.07 1.08s.03.74.07 1.08l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65z" />
			</svg>
		</a>
	</div>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'admin-dashboard',
	'dashboard' => [
		'monthlyDonations' => [
			'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
			'values' => [3, 4, 5.5, 7, 9, 11, 13, 15, 17, 18.5, 19.5, 20.5],
			'maxY' => 25,
			'stepY' => 5,
		],
		'bloodTypeDistribution' => [
			['label' => 'O+', 'value' => 0.38, 'color' => '#b60c0c'],
			['label' => 'A+', 'value' => 0.29, 'color' => '#5a0000'],
			['label' => 'B+', 'value' => 0.20, 'color' => '#e83333'],
			['label' => 'AB+', 'value' => 0.08, 'color' => '#f07070'],
			['label' => 'O-', 'value' => 0.05, 'color' => '#ffd0d0'],
		],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	<div class="main container-fluid px-0">
		<main class="content container-fluid py-3">
			<section class="dashboard-stats row" aria-label="Dashboard statistics">
				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--red h-100">
						<div class="stat-card__icons">
							<span class="stat-card__icon-main" aria-hidden="true">&#128101;</span>
							<span class="stat-card__icon-aux" aria-hidden="true">&#8599;</span>
						</div>
						<p class="stat-card__label stat-card__label--white">Total Donors</p>
						<p class="stat-card__value stat-card__value--white">10,143</p>
						<p class="stat-card__change stat-card__change--white">+12% this month</p>
					</div>
				</div>

				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--green h-100">
						<div class="stat-card__icons">
							<span class="stat-card__icon-main" aria-hidden="true">&#10004;</span>
							<span class="stat-card__icon-aux" aria-hidden="true">&#128202;</span>
						</div>
						<p class="stat-card__label">Successful Donations</p>
						<p class="stat-card__value">25,143</p>
						<p class="stat-card__change stat-card__change--green">+12% this month</p>
					</div>
				</div>

				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--blue h-100">
						<div class="stat-card__icons">
							<span class="stat-card__icon-main" aria-hidden="true">&#128197;</span>
							<span class="stat-card__icon-aux" aria-hidden="true">&#128339;</span>
						</div>
						<p class="stat-card__label">Upcoming Appointments</p>
						<p class="stat-card__value">143</p>
						<p class="stat-card__change stat-card__change--blue">+12% this month</p>
					</div>
				</div>

				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--gold h-100">
						<div class="stat-card__icons">
							<span class="stat-card__icon-main" aria-hidden="true">&#129656;</span>
							<span class="stat-card__icon-aux" aria-hidden="true">&#128204;</span>
						</div>
						<p class="stat-card__label">Donation Records</p>
						<p class="stat-card__value">1,921</p>
						<p class="stat-card__change stat-card__change--gold">+12% this month</p>
					</div>
				</div>
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
				<button class="map-banner__btn btn" type="button">View Map</button>
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
						<li class="activity-item">
							<span class="activity-item__dot activity-item__dot--green"></span>
							<div class="activity-item__info">
								<p class="activity-item__name">John Smith</p>
								<p class="activity-item__action">Completed Donation</p>
							</div>
							<span class="activity-item__time">5 min ago</span>
						</li>
						<li class="activity-item">
							<span class="activity-item__dot activity-item__dot--blue"></span>
							<div class="activity-item__info">
								<p class="activity-item__name">Chelsea Marie</p>
								<p class="activity-item__action">Booked Appointment</p>
							</div>
							<span class="activity-item__time">1 hour ago</span>
						</li>
						<li class="activity-item">
							<span class="activity-item__dot activity-item__dot--gold"></span>
							<div class="activity-item__info">
								<p class="activity-item__name">John Cena</p>
								<p class="activity-item__action">Cancelled Appointment</p>
							</div>
							<span class="activity-item__time">5 hours ago</span>
						</li>
						<li class="activity-item">
							<span class="activity-item__dot activity-item__dot--red"></span>
							<div class="activity-item__info">
								<p class="activity-item__name">Johnson Dwyane</p>
								<p class="activity-item__action">Registration Complete</p>
							</div>
							<span class="activity-item__time">20 min ago</span>
						</li>
					</ul>
					<button class="panel__footer-btn panel__footer-btn--red btn" type="button">View All Activities</button>
					</div>
				</div>

				<div class="col-12 col-lg-6">
					<div class="panel h-100">
					<h2 class="panel__title">Pending Approvals</h2>
					<ul class="approval-list">
						<li class="approval-item">
							<div class="approval-item__info">
								<p class="approval-item__name">Michael Jackson</p>
								<p class="approval-item__type">New Registration</p>
							</div>
							<div class="approval-item__actions">
								<button class="btn-approve btn" type="button">Approve</button>
								<button class="btn-review btn" type="button">Review</button>
							</div>
						</li>
						<li class="approval-item">
							<div class="approval-item__info">
								<p class="approval-item__name">Noli De Castro</p>
								<p class="approval-item__type">Eligibility Review</p>
							</div>
							<div class="approval-item__actions">
								<button class="btn-approve btn" type="button">Approve</button>
								<button class="btn-review btn" type="button">Review</button>
							</div>
						</li>
						<li class="approval-item">
							<div class="approval-item__info">
								<p class="approval-item__name">Hev Abi</p>
								<p class="approval-item__type">Appointment Change</p>
							</div>
							<div class="approval-item__actions">
								<button class="btn-approve btn" type="button">Approve</button>
								<button class="btn-review btn" type="button">Review</button>
							</div>
						</li>
					</ul>
					<button class="panel__footer-btn panel__footer-btn--green btn" type="button">View All Approvals</button>
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
				: [3, 4, 5.5, 7, 9, 11, 13, 15, 17, 18.5, 19.5, 20.5];
			var yMax = Number(lineChartData.maxY || 25);
			var yStep = Number(lineChartData.stepY || 5);

			ctx.clearRect(0, 0, w, h);
			ctx.font = '11px Poppins, sans-serif';
			ctx.fillStyle = '#666';
			ctx.textAlign = 'right';
			ctx.textBaseline = 'middle';
			ctx.strokeStyle = '#e8e8e8';
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
			ctx.fillStyle = '#333';

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
				ctx.strokeStyle = '#fff';
				ctx.lineWidth = 2;
				ctx.fill();
				ctx.stroke();
			}

			ctx.fillStyle = '#b60c0c';
			ctx.fillRect(w - 120, 14, 12, 12);
			ctx.fillStyle = '#333';
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

			var fallbackSegments = [
				{ label: 'O+', value: 0.38, color: '#b60c0c' },
				{ label: 'A+', value: 0.29, color: '#5a0000' },
				{ label: 'B+', value: 0.20, color: '#e83333' },
				{ label: 'AB+', value: 0.08, color: '#f07070' },
				{ label: 'O-', value: 0.05, color: '#ffd0d0' }
			];

			var segmentSource = Array.isArray(dashboardData.bloodTypeDistribution) && dashboardData.bloodTypeDistribution.length
				? dashboardData.bloodTypeDistribution
				: fallbackSegments;

			var segments = segmentSource.map(function (item) {
				return {
					label: String(item.label || ''),
					value: Number(item.value || 0),
					color: item.color || '#b60c0c'
				};
			});

			var legendW = 112;
			var chartW = w - legendW;
			var cx = chartW / 2;
			var cy = h / 2;
			var outerR = Math.min(chartW, h) / 2 - 18;
			var innerR = outerR * 0.45;

			ctx.clearRect(0, 0, w, h);

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
				ctx.strokeStyle = '#fff';
				ctx.lineWidth = 2;
				ctx.stroke();
				start = end;
			}

			ctx.beginPath();
			ctx.arc(cx, cy, innerR, 0, Math.PI * 2);
			ctx.fillStyle = '#fff';
			ctx.fill();

			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.font = '700 13px Poppins, sans-serif';
			ctx.fillStyle = '#333';
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
				ctx.fillStyle = '#333';
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

		renderCharts();
	})();
</script>
@endpush

