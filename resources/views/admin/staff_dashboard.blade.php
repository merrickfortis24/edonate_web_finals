@extends('layouts.admin')

@section('title', 'eDonate - Staff Dashboard')
@section('admin_page_class', 'staff-dashboard-page')
@section('layout_wrapper_class', 'app')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('render_default_hamburger', 'false')

@section('header_title', 'Staff Dashboard')
@section('header_subtitle', 'Blood Donation Management System - Staff Portal')

@section('header_slot')
	<button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
	</button>
@endsection

@section('header_actions')
	<div class="header__date-group" aria-label="Signed-in role">
		<p class="header__date-label">Access Level</p>
		<p class="header__date-value text-capitalize">{{ session('admin_role', 'staff') }}</p>
	</div>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'staff-dashboard',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	<div class="main container-fluid px-0">
		<main class="content container-fluid py-3">
			<div class="alert alert-info" role="alert">
				You are signed in as a staff user. You can access operational modules assigned to staff accounts.
			</div>

			<section class="row g-3" aria-label="Staff quick access">
				<div class="col-12 col-md-6 col-xl-4">
					<a href="{{ route('admin.appointments') }}" class="text-decoration-none">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body">
								<h2 class="h5 mb-2">Appointment Management</h2>
								<p class="mb-0 text-muted">Review, approve, or update donor appointments.</p>
							</div>
						</div>
					</a>
				</div>

				<div class="col-12 col-md-6 col-xl-4">
					<a href="{{ route('admin.donation-records') }}" class="text-decoration-none">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body">
								<h2 class="h5 mb-2">Donation Records</h2>
								<p class="mb-0 text-muted">Track and verify submitted blood donation logs.</p>
							</div>
						</div>
					</a>
				</div>

				<div class="col-12 col-md-6 col-xl-4">
					<a href="{{ route('admin.blood-availability-mapping') }}" class="text-decoration-none">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body">
								<h2 class="h5 mb-2">Blood Availability Mapping</h2>
								<p class="mb-0 text-muted">View blood stock levels by center and blood type.</p>
							</div>
						</div>
					</a>
				</div>

				<div class="col-12 col-md-6 col-xl-4">
					<a href="{{ route('admin.notification-center') }}" class="text-decoration-none">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body">
								<h2 class="h5 mb-2">Notification Center</h2>
								<p class="mb-0 text-muted">Publish reminders and updates for donors and staff.</p>
							</div>
						</div>
					</a>
				</div>

				<div class="col-12 col-md-6 col-xl-4">
					<a href="{{ route('admin.audit-logs') }}" class="text-decoration-none">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body">
								<h2 class="h5 mb-2">Audit Logs</h2>
								<p class="mb-0 text-muted">Inspect action history for system accountability.</p>
							</div>
						</div>
					</a>
				</div>
			</section>
		</main>
	</div>
@endsection
