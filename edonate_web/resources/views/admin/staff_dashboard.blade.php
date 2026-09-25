@extends('layouts.admin')

@section('title', 'eDonate - Staff Dashboard')
@section('admin_page_class', 'admin-dashboard-page staff-dashboard-page')
@section('header_title', 'Staff Dashboard')
@section('header_subtitle', 'Blood Donation Management System - Staff Portal')

@section('header_actions')
	<div class="header__date-group" aria-label="Current date">
		<p class="header__date-label">Today's Date</p>
		<p class="header__date-value" id="todayDate">{{ now()->format('F j, Y') }}</p>
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
		'kpis' => data_get($staffDashboardPayload ?? [], 'metrics', []),
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	@php
		$metrics = data_get($staffDashboardPayload ?? [], 'metrics', []);
		$activities = data_get($staffDashboardPayload ?? [], 'activities', []);
		$activityAvailable = (bool) data_get($staffDashboardPayload ?? [], 'activity_available', false);
		$metricCards = [
			['key' => 'appointments_today', 'label' => 'Appointments Today', 'note' => 'All scheduled appointments', 'class' => 'red'],
			['key' => 'confirmed_today', 'label' => 'Confirmed Today', 'note' => 'Confirmed donor schedules', 'class' => 'green'],
			['key' => 'checked_in_today', 'label' => 'Checked In', 'note' => 'Today’s arrived donors', 'class' => 'blue'],
			['key' => 'completed_today', 'label' => 'Completed Donations', 'note' => 'Recorded today', 'class' => 'gold'],
			['key' => 'deferred_today', 'label' => 'Deferred Today', 'note' => 'On-site deferrals', 'class' => 'red'],
			['key' => 'open_requests', 'label' => 'Open Blood Requests', 'note' => 'Open or in progress', 'class' => 'green'],
			['key' => 'emergency_requests', 'label' => 'Emergency Requests', 'note' => 'Open emergency requests', 'class' => 'blue'],
			['key' => 'inventory_alerts', 'label' => 'Inventory Alerts', 'note' => 'Low or out-of-stock types', 'class' => 'gold'],
			['key' => 'active_facilities', 'label' => 'Active Facilities', 'note' => 'Current facility snapshot', 'class' => 'red'],
		];
	@endphp
	<div class="main container-fluid px-0">
		<main class="content container-fluid py-3">
			<section class="dashboard-stats row g-3" aria-label="Staff dashboard statistics">
				@foreach ($metricCards as $card)
					@php
						$metric = data_get($metrics, $card['key'], []);
						$available = (bool) data_get($metric, 'available', false);
					@endphp
					<div class="col-6 col-lg-3">
						<div class="stat-card stat-card--{{ $card['class'] }} h-100">
							<p class="stat-card__label">{{ $card['label'] }}</p>
							<p class="stat-card__value">{{ $available ? number_format((int) data_get($metric, 'value', 0)) : 'N/A' }}</p>
							<p class="stat-card__change">{{ $available ? $card['note'] : 'Unable to load this metric' }}</p>
						</div>
					</div>
				@endforeach
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
							@forelse ($activities as $activity)
								<li class="activity-item">
									<span class="activity-item__dot activity-item__dot--blue"></span>
									<div class="activity-item__info">
										<p class="activity-item__name">{{ $activity['title'] }}</p>
										<p class="activity-item__action">{{ $activity['description'] ?: 'No description recorded' }}@if($activity['actor']) · {{ $activity['actor'] }}@endif</p>
									</div>
									<span class="activity-item__time">{{ $activity['created_at'] ? \Illuminate\Support\Carbon::parse($activity['created_at'])->diffForHumans() : '' }}</span>
								</li>
							@empty
								<li class="activity-item text-muted py-3">
									{{ $activityAvailable ? 'No recent activity.' : 'Recent activity is unavailable.' }}
								</li>
							@endforelse
						</ul>
						<a class="panel__footer-btn panel__footer-btn--green btn" href="{{ route('admin.audit-logs') }}">View Audit Logs</a>
					</div>
				</div>
			</section>
		</main>
	</div>
@endsection
