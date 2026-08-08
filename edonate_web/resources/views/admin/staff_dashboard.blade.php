@extends('layouts.admin')

@section('title', 'eDonate - Staff Dashboard')
@section('admin_page_class', 'admin-dashboard-page staff-dashboard-page')
@section('header_title', 'Staff Dashboard')
@section('header_subtitle', 'Blood Donation Management System - Staff Portal')

@section('header_actions')
	<div class="header__date-group" aria-label="Current date">
		<p class="header__date-label">Today's Date</p>
		<p class="header__date-value" id="todayDate">-</p>
	</div>

	<div class="header__icon-actions" aria-label="Staff quick actions">
		<a class="header__icon-btn" href="{{ route('admin.notification-center') }}" aria-label="Go to Notification Center">
			<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
				<path d="M12 2C10.07 2 8.32 2.85 7.14 4.21L3 8.99V15H5V20H19V15H21V8.99L16.86 4.21C15.68 2.85 13.93 2 12 2ZM12 4C13.38 4 14.63 4.57 15.52 5.5H8.48C9.37 4.57 10.62 4 12 4ZM5 10.41L8.14 7H15.86L19 10.41V13H5V10.41ZM7 15H17V18H7V15Z" />
			</svg>
		</a>

		<a class="header__icon-btn" href="{{ route('admin.audit-logs') }}" aria-label="Go to Audit Logs">
			<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
				<path d="M19 3H5C3.89 3 3 3.9 3 5V19C3 20.1 3.89 21 5 21H19C20.11 21 21 20.1 21 19V5C21 3.9 20.11 3 19 3ZM11 17H7V15H11V17ZM17 13H7V11H17V13ZM17 9H7V7H17V9Z" />
			</svg>
		</a>
	</div>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'staff-dashboard',
	'dashboard' => [
		'kpis' => [
			'appointmentsToday' => 42,
			'pendingDonations' => 18,
			'stockAlerts' => 6,
			'notificationsSent' => 27,
		],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	<div class="main container-fluid px-0">
		<main class="content container-fluid py-3">
			<section class="dashboard-stats row" aria-label="Staff dashboard statistics">
				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--red h-100">
						<p class="stat-card__label stat-card__label--white">Appointments Today</p>
						<p class="stat-card__value stat-card__value--white" id="staffStatAppointments">0</p>
						<p class="stat-card__change stat-card__change--white">Operational queue</p>
					</div>
				</div>

				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--green h-100">
						<p class="stat-card__label">Pending Donations</p>
						<p class="stat-card__value" id="staffStatPendingDonations">0</p>
						<p class="stat-card__change stat-card__change--green">For verification</p>
					</div>
				</div>

				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--blue h-100">
						<p class="stat-card__label">Stock Alerts</p>
						<p class="stat-card__value" id="staffStatStockAlerts">0</p>
						<p class="stat-card__change stat-card__change--blue">Needs attention</p>
					</div>
				</div>

				<div class="col-6 col-lg-3">
					<div class="stat-card stat-card--gold h-100">
						<p class="stat-card__label">Notifications Sent</p>
						<p class="stat-card__value" id="staffStatNotifications">0</p>
						<p class="stat-card__change stat-card__change--gold">Today</p>
					</div>
				</div>
			</section>

			<section class="map-banner d-flex align-items-center" aria-label="Blood map quick access">
				<div class="map-banner__pin" aria-hidden="true">
					<svg width="22" height="28" viewBox="0 0 22 28" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M11 0C4.925 0 0 4.925 0 11c0 8.25 11 17 11 17s11-8.75 11-17C22 4.925 17.075 0 11 0zm0 14.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z" fill="white"/>
					</svg>
				</div>
				<div class="map-banner__text">
					<p class="map-banner__title">Blood Availability Mapping</p>
					<p class="map-banner__subtitle">Check branch stock levels and coordinate urgent needs faster</p>
				</div>
				<a class="map-banner__btn btn" href="{{ route('admin.blood-availability-mapping') }}">Open Map</a>
			</section>

			<section class="dashboard-bottom row" aria-label="Staff operations and quick access">
				<div class="col-12 col-lg-6">
					<div class="panel h-100">
						<h2 class="panel__title">Operational Modules</h2>
						<ul class="approval-list">
							<li class="approval-item">
								<div class="approval-item__info">
									<p class="approval-item__name">Appointment Management</p>
									<p class="approval-item__type">Review and process donor schedules</p>
								</div>
								<div class="approval-item__actions">
									<a class="btn-review btn" href="{{ route('admin.appointments') }}">Open</a>
								</div>
							</li>
							<li class="approval-item">
								<div class="approval-item__info">
									<p class="approval-item__name">Donation Records</p>
									<p class="approval-item__type">Verify and update donation submissions</p>
								</div>
								<div class="approval-item__actions">
									<a class="btn-review btn" href="{{ route('admin.donation-records') }}">Open</a>
								</div>
							</li>
							<li class="approval-item">
								<div class="approval-item__info">
									<p class="approval-item__name">Notification Center</p>
									<p class="approval-item__type">Send reminders and operational updates</p>
								</div>
								<div class="approval-item__actions">
									<a class="btn-review btn" href="{{ route('admin.notification-center') }}">Open</a>
								</div>
							</li>
						</ul>
						<a class="panel__footer-btn panel__footer-btn--red btn" href="{{ route('admin.appointments') }}">Go to Operations</a>
					</div>
				</div>

				<div class="col-12 col-lg-6">
					<div class="panel h-100">
						<h2 class="panel__title">Recent Staff Activities</h2>
						<ul class="activity-list">
							<li class="activity-item">
								<span class="activity-item__dot activity-item__dot--green"></span>
								<div class="activity-item__info">
									<p class="activity-item__name">Appointment queue updated</p>
									<p class="activity-item__action">3 donors marked ready for screening</p>
								</div>
								<span class="activity-item__time">12 min ago</span>
							</li>
							<li class="activity-item">
								<span class="activity-item__dot activity-item__dot--blue"></span>
								<div class="activity-item__info">
									<p class="activity-item__name">Donation record validated</p>
									<p class="activity-item__action">Bag tracking data verified</p>
								</div>
								<span class="activity-item__time">45 min ago</span>
							</li>
							<li class="activity-item">
								<span class="activity-item__dot activity-item__dot--gold"></span>
								<div class="activity-item__info">
									<p class="activity-item__name">Low stock alert reviewed</p>
									<p class="activity-item__action">O- inventory escalated</p>
								</div>
								<span class="activity-item__time">1 hr ago</span>
							</li>
						</ul>
						<a class="panel__footer-btn panel__footer-btn--green btn" href="{{ route('admin.audit-logs') }}">View Audit Logs</a>
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
		var kpis = (dashboardData && dashboardData.kpis && typeof dashboardData.kpis === 'object') ? dashboardData.kpis : {};

		var statAppointments = document.getElementById('staffStatAppointments');
		var statPendingDonations = document.getElementById('staffStatPendingDonations');
		var statStockAlerts = document.getElementById('staffStatStockAlerts');
		var statNotifications = document.getElementById('staffStatNotifications');

		if (statAppointments) {
			statAppointments.textContent = String(Number(kpis.appointmentsToday || 0));
		}
		if (statPendingDonations) {
			statPendingDonations.textContent = String(Number(kpis.pendingDonations || 0));
		}
		if (statStockAlerts) {
			statStockAlerts.textContent = String(Number(kpis.stockAlerts || 0));
		}
		if (statNotifications) {
			statNotifications.textContent = String(Number(kpis.notificationsSent || 0));
		}
	})();
</script>
@endpush
