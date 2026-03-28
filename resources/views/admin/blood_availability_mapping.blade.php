@extends('layouts.admin')

@section('title', 'eDonate - Geographic Blood Availability')
@section('admin_page_class', 'admin-blood-availability-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('sidebar_open_class', 'is-open')
@section('hamburger_class', 'sidebar__hamburger hamburger')

@section('header_title', 'Geographic Blood Availability')
@section('header_subtitle', 'Monitor blood type availability across different locations in real-time')
@section('header_class', 'page-header')
@section('header_left_class', 'page-header__left')
@section('header_right_class', 'page-header__date')

@section('header_actions')
	<p class="page-header__date-label">Today's Date</p>
	<p class="page-header__date-value" id="todayDate">-</p>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'blood-availability-mapping',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
<main class="main container-fluid px-0">
	<section class="panels-row" aria-label="Filters and Location Map">
		<div class="filter-panel">
			<div class="filter-panel__header">
				<svg class="filter-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<path d="M4 6H20M7 12H17M10 18H14" stroke="#b60c0c" stroke-width="2" stroke-linecap="round"/>
				</svg>
				<span class="filter-panel__title">Filters</span>
			</div>

			<p class="filter-panel__label">Blood Type</p>
			<div class="filter-panel__select-wrap">
				<select class="filter-panel__select form-select" aria-label="Filter by blood type">
					<option value="A+">A+</option>
					<option value="A-">A-</option>
					<option value="B+">B+</option>
					<option value="B-">B-</option>
					<option value="AB+">AB+</option>
					<option value="AB-">AB-</option>
					<option value="O+">O+</option>
					<option value="O-">O-</option>
				</select>
				<span class="filter-panel__select-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M6 9l6 6 6-6" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
			</div>

			<p class="filter-panel__label">Barangay</p>
			<div class="filter-panel__select-wrap">
				<select class="filter-panel__select form-select" aria-label="Filter by barangay">
					<option value="Balintawak">Balintawak</option>
					<option value="Marawoy">Marawoy</option>
					<option value="Sabang">Sabang</option>
				</select>
				<span class="filter-panel__select-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M6 9l6 6 6-6" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
			</div>

			<button class="filter-panel__clear-btn btn btn-outline-secondary" type="button">Clear Filters</button>

			<hr class="filter-panel__divider" />

			<p class="filter-panel__section-label">Active Filters</p>
			<div class="filter-chips">
				<span class="filter-chip filter-chip--type">A+</span>
				<span class="filter-chip filter-chip--barangay">Balintawak</span>
			</div>

			<hr class="filter-panel__divider" />

			<p class="filter-panel__section-label">Quick Stats:</p>
			<div class="quick-stats__row">
				<span class="quick-stats__key">Total Locations:</span>
				<span class="quick-stats__val">1</span>
			</div>
		</div>

		<div class="map-panel">
			<p class="map-panel__title">Location Map</p>
			<p class="map-panel__subtitle">Click on a location marker to view blood availability details</p>
			<div class="map-panel__map-wrap">
				<img
					class="map-panel__map-img"
					src="https://www.figma.com/api/mcp/asset/1f6c4f5a-0665-4907-a627-86a7236d9a50"
					alt="Map of Lipa City, Batangas, Philippines showing blood availability locations"
				/>
				<div class="map-legend" aria-label="Map legend">
					<p class="map-legend__title">Availability Level</p>
					<div class="map-legend__row">
						<span class="map-legend__dot map-legend__dot--high"></span>
						<span class="map-legend__text">High (50+ units)</span>
					</div>
					<div class="map-legend__row">
						<span class="map-legend__dot map-legend__dot--medium"></span>
						<span class="map-legend__text">Medium (20-49 units)</span>
					</div>
					<div class="map-legend__row">
						<span class="map-legend__dot map-legend__dot--low"></span>
						<span class="map-legend__text">Low (10-19 units)</span>
					</div>
					<div class="map-legend__row">
						<span class="map-legend__dot map-legend__dot--critical"></span>
						<span class="map-legend__text">Critical (&lt;10 units)</span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="location-panel" aria-label="Lipa Medix blood availability">
		<div class="location-panel__header">
			<h2 class="location-panel__name">Lipa Medix</h2>
			<span class="location-panel__badge">Critical Shortage</span>
		</div>
		<p class="location-panel__address">Marawoy, Lipa City, Batangas</p>

		<div class="blood-grid">
			<div class="blood-card blood-card--green">
				<div class="blood-card__top">
					<span class="blood-card__type">A+</span>
					<img class="blood-card__icon" src="https://www.figma.com/api/mcp/asset/f87d2b3b-cdcf-4001-9c9d-8a026fff7b7e" alt="Blood drop icon" />
				</div>
				<span class="blood-card__count">45</span>
				<div class="blood-card__status-row">
					<span class="blood-card__dot blood-card__dot--high"></span>
					<span class="blood-card__status-label">High</span>
				</div>
			</div>

			<div class="blood-card blood-card--orange">
				<div class="blood-card__top">
					<span class="blood-card__type">A-</span>
					<img class="blood-card__icon" src="https://www.figma.com/api/mcp/asset/f87d2b3b-cdcf-4001-9c9d-8a026fff7b7e" alt="Blood drop icon" />
				</div>
				<span class="blood-card__count">8</span>
				<div class="blood-card__status-row">
					<span class="blood-card__dot blood-card__dot--low"></span>
					<span class="blood-card__status-label">Low</span>
				</div>
			</div>

			<div class="blood-card blood-card--yellow">
				<div class="blood-card__top">
					<span class="blood-card__type">B+</span>
					<img class="blood-card__icon" src="https://www.figma.com/api/mcp/asset/f87d2b3b-cdcf-4001-9c9d-8a026fff7b7e" alt="Blood drop icon" />
				</div>
				<span class="blood-card__count">32</span>
				<div class="blood-card__status-row">
					<span class="blood-card__dot blood-card__dot--medium"></span>
					<span class="blood-card__status-label">Medium</span>
				</div>
			</div>

			<div class="blood-card blood-card--red">
				<div class="blood-card__top">
					<span class="blood-card__type">B-</span>
					<img class="blood-card__icon" src="https://www.figma.com/api/mcp/asset/f87d2b3b-cdcf-4001-9c9d-8a026fff7b7e" alt="Blood drop icon" />
				</div>
				<span class="blood-card__count">5</span>
				<div class="blood-card__status-row">
					<span class="blood-card__dot blood-card__dot--critical"></span>
					<span class="blood-card__status-label">Critical</span>
				</div>
				<button class="blood-card__request-btn blood-card__request-btn--dark btn" type="button">Request Blood</button>
			</div>

			<div class="blood-card blood-card--red">
				<div class="blood-card__top">
					<span class="blood-card__type">AB+</span>
					<img class="blood-card__icon" src="https://www.figma.com/api/mcp/asset/f87d2b3b-cdcf-4001-9c9d-8a026fff7b7e" alt="Blood drop icon" />
				</div>
				<span class="blood-card__count">3</span>
				<div class="blood-card__status-row">
					<span class="blood-card__dot blood-card__dot--critical"></span>
					<span class="blood-card__status-label">Critical</span>
				</div>
				<button class="blood-card__request-btn btn" type="button">Request Blood</button>
			</div>

			<div class="blood-card blood-card--yellow">
				<div class="blood-card__top">
					<span class="blood-card__type">AB-</span>
					<img class="blood-card__icon" src="https://www.figma.com/api/mcp/asset/f87d2b3b-cdcf-4001-9c9d-8a026fff7b7e" alt="Blood drop icon" />
				</div>
				<span class="blood-card__count">18</span>
				<div class="blood-card__status-row">
					<span class="blood-card__dot blood-card__dot--medium"></span>
					<span class="blood-card__status-label">Medium</span>
				</div>
			</div>

			<div class="blood-card blood-card--green">
				<div class="blood-card__top">
					<span class="blood-card__type">O+</span>
					<img class="blood-card__icon" src="https://www.figma.com/api/mcp/asset/f87d2b3b-cdcf-4001-9c9d-8a026fff7b7e" alt="Blood drop icon" />
				</div>
				<span class="blood-card__count">67</span>
				<div class="blood-card__status-row">
					<span class="blood-card__dot blood-card__dot--high"></span>
					<span class="blood-card__status-label">High</span>
				</div>
			</div>

			<div class="blood-card blood-card--orange">
				<div class="blood-card__top">
					<span class="blood-card__type">O-</span>
					<img class="blood-card__icon" src="https://www.figma.com/api/mcp/asset/f87d2b3b-cdcf-4001-9c9d-8a026fff7b7e" alt="Blood drop icon" />
				</div>
				<span class="blood-card__count">12</span>
				<div class="blood-card__status-row">
					<span class="blood-card__dot blood-card__dot--low"></span>
					<span class="blood-card__status-label">Low</span>
				</div>
			</div>
		</div>

		<p class="location-panel__footer">Last updated: 2026-01-27 08:30 AM</p>
	</section>
</main>
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
	})();
</script>
@endpush
