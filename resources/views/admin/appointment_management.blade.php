@extends('layouts.admin')

@section('title', 'eDonate - Appointment Management')
@section('admin_page_class', 'admin-appointments-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_id', 'appointmentSidebar')
@section('sidebar_aria_label', 'Admin navigation')
@section('sidebar_nav_aria_label', 'Primary navigation')
@section('sidebar_link_mode', 'link')
@section('sidebar_open_class', 'is-open')
@section('overlay_id', 'appointmentOverlay')
@section('overlay_class', 'appointment-overlay overlay')
@section('overlay_open_class', 'is-visible')
@section('hamburger_id', 'appointmentHamburger')
@section('render_default_hamburger', 'false')

@section('header_title', 'Appointment Management')
@section('header_subtitle', 'Approve, reject, or reschedule donation appointments')

@section('header_slot')
	<button class="hamburger" id="appointmentHamburger" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="appointmentSidebar">
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
	</button>
@endsection

@section('header_actions')
	<div class="appointment-header__views" role="group" aria-label="Appointment view mode">
		<button class="appointment-view-btn appointment-view-btn--active btn" type="button">List View</button>
		<button class="appointment-view-btn appointment-view-btn--outline btn" type="button">Calendar View</button>
	</div>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'appointment-management',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	<main class="appointment-main container-fluid px-0">
		<section class="appointment-content container-fluid py-3" aria-label="Appointments content">
			<div class="appointment-stats row g-3" aria-label="Appointment summary">
				<div class="col-6 col-xl-3">
					<article class="stat-card appointment-stat appointment-stat--green h-100">
						<p class="appointment-stat__label">Confirmed</p>
						<p class="appointment-stat__value">5</p>
					</article>
				</div>
				<div class="col-6 col-xl-3">
					<article class="stat-card appointment-stat appointment-stat--gold h-100">
						<p class="appointment-stat__label">Pending Approval</p>
						<p class="appointment-stat__value">3</p>
					</article>
				</div>
				<div class="col-6 col-xl-3">
					<article class="stat-card appointment-stat appointment-stat--red h-100">
						<p class="appointment-stat__label">Cancelled</p>
						<p class="appointment-stat__value">1</p>
					</article>
				</div>
				<div class="col-6 col-xl-3">
					<article class="stat-card appointment-stat appointment-stat--blue h-100">
						<p class="appointment-stat__label">Rescheduled</p>
						<p class="appointment-stat__value">1</p>
					</article>
				</div>
			</div>

			<form class="appointment-filter row g-3 align-items-center" role="search" aria-label="Filter appointments" action="#" method="get" onsubmit="return false;">
				<div class="appointment-filter__search col-12 col-lg">
					<span class="appointment-filter__search-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" aria-hidden="true">
							<circle cx="11" cy="11" r="7"></circle>
							<line x1="16.5" y1="16.5" x2="22" y2="22"></line>
						</svg>
					</span>
					<input type="search" class="appointment-filter__input form-control" placeholder="Search by name, ID, or email..." aria-label="Search appointments">
				</div>

				<div class="appointment-filter__select-wrap appointment-filter__select-wrap--center col-12 col-md-6 col-xl-3">
					<span class="appointment-filter__select-icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M10 2C7.23858 2 5 4.23858 5 7C5 10.5 10 17 10 17C10 17 15 10.5 15 7C15 4.23858 12.7614 2 10 2Z" stroke="#333" stroke-width="1.5"></path>
							<circle cx="10" cy="7" r="2" stroke="#333" stroke-width="1.5"></circle>
						</svg>
					</span>
					<select class="appointment-filter__select form-select" aria-label="Filter by center" name="center">
						<option value="">Filter By Center</option>
						<option value="lipa">Lipa Medix</option>
					</select>
					<span class="appointment-filter__chevron" aria-hidden="true">
						<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
					</span>
				</div>

				<div class="appointment-filter__select-wrap appointment-filter__select-wrap--status col-12 col-md-6 col-xl-2">
					<span class="appointment-filter__select-icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M10 3C10 3 5 9 5 13C5 15.7614 7.23858 18 10 18C12.7614 18 15 15.7614 15 13C15 9 10 3 10 3Z" stroke="#b60c0c" stroke-width="1.5"></path>
						</svg>
					</span>
					<select class="appointment-filter__select form-select" aria-label="Filter by status" name="status">
						<option value="">All Status</option>
						<option value="confirmed">Confirmed</option>
						<option value="pending">Pending</option>
						<option value="cancelled">Cancelled</option>
						<option value="rescheduled">Rescheduled</option>
					</select>
					<span class="appointment-filter__chevron" aria-hidden="true">
						<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
					</span>
				</div>
			</form>

			<section class="appointment-table" aria-label="Appointment list">
				<div class="appointment-table-scroll table-responsive">
					<div class="appointment-table__head" role="rowgroup">
						<div class="appointment-table__head-cell">Appointment ID</div>
						<div class="appointment-table__head-cell">Donor</div>
						<div class="appointment-table__head-cell">Date &amp; Time</div>
						<div class="appointment-table__head-cell">Center</div>
						<div class="appointment-table__head-cell">Status</div>
						<div class="appointment-table__head-cell">Actions</div>
					</div>

					<div class="appointment-table__body" role="rowgroup">
						<div class="appointment-row" role="row">
							<div class="appointment-cell"><span class="appointment-id">AP001</span></div>
							<div class="appointment-cell">
								<span class="appointment-donor__name">John Smith</span>
								<span class="appointment-donor__meta">D001 &bull; O+</span>
							</div>
							<div class="appointment-cell">
								<span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>Jan 10, 2026</span>
								<span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>10:00 AM</span>
							</div>
							<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>Lipa Medix</div>
							<div class="appointment-cell"><span class="appointment-badge appointment-badge--confirmed">Confirmed</span></div>
							<div class="appointment-cell appointment-actions"><button class="appointment-btn appointment-btn--reschedule" type="button"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10a6 6 0 1 1 1.76 4.24" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round"></path><polyline points="4 14 4 10 8 10" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>Reschedule</button></div>
						</div>

						<div class="appointment-row" role="row">
							<div class="appointment-cell"><span class="appointment-id">AP002</span></div>
							<div class="appointment-cell"><span class="appointment-donor__name">John Smith</span><span class="appointment-donor__meta">D001 &bull; O+</span></div>
							<div class="appointment-cell"><span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>Jan 10, 2026</span><span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>10:00 AM</span></div>
							<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>Lipa Medix</div>
							<div class="appointment-cell"><span class="appointment-badge appointment-badge--pending">Pending</span></div>
							<div class="appointment-cell appointment-actions"><button class="appointment-btn appointment-btn--approve" type="button"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>Approve</button><button class="appointment-btn appointment-btn--reject" type="button"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="#b60c0c" stroke-width="1.8"></circle><line x1="15" y1="9" x2="9" y2="15" stroke="#b60c0c" stroke-width="1.8" stroke-linecap="round"></line><line x1="9" y1="9" x2="15" y2="15" stroke="#b60c0c" stroke-width="1.8" stroke-linecap="round"></line></svg>Reject</button></div>
						</div>

						<div class="appointment-row" role="row">
							<div class="appointment-cell"><span class="appointment-id">AP003</span></div>
							<div class="appointment-cell"><span class="appointment-donor__name">John Smith</span><span class="appointment-donor__meta">D001 &bull; O+</span></div>
							<div class="appointment-cell"><span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>Jan 10, 2026</span><span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>10:00 AM</span></div>
							<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>Lipa Medix</div>
							<div class="appointment-cell"><span class="appointment-badge appointment-badge--pending">Pending</span></div>
							<div class="appointment-cell appointment-actions"><button class="appointment-btn appointment-btn--approve" type="button"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>Approve</button><button class="appointment-btn appointment-btn--reject" type="button"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="#b60c0c" stroke-width="1.8"></circle><line x1="15" y1="9" x2="9" y2="15" stroke="#b60c0c" stroke-width="1.8" stroke-linecap="round"></line><line x1="9" y1="9" x2="15" y2="15" stroke="#b60c0c" stroke-width="1.8" stroke-linecap="round"></line></svg>Reject</button></div>
						</div>

						<div class="appointment-row" role="row">
							<div class="appointment-cell"><span class="appointment-id">AP004</span></div>
							<div class="appointment-cell"><span class="appointment-donor__name">John Smith</span><span class="appointment-donor__meta">D001 &bull; O+</span></div>
							<div class="appointment-cell"><span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>Jan 10, 2026</span><span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>10:00 AM</span></div>
							<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>Lipa Medix</div>
							<div class="appointment-cell"><span class="appointment-badge appointment-badge--confirmed">Confirmed</span></div>
							<div class="appointment-cell appointment-actions"><button class="appointment-btn appointment-btn--reschedule" type="button"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10a6 6 0 1 1 1.76 4.24" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round"></path><polyline points="4 14 4 10 8 10" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>Reschedule</button></div>
						</div>

						<div class="appointment-row" role="row">
							<div class="appointment-cell"><span class="appointment-id">AP005</span></div>
							<div class="appointment-cell"><span class="appointment-donor__name">John Smith</span><span class="appointment-donor__meta">D001 &bull; O+</span></div>
							<div class="appointment-cell"><span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>Jan 10, 2026</span><span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>10:00 AM</span></div>
							<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>Lipa Medix</div>
							<div class="appointment-cell"><span class="appointment-badge appointment-badge--confirmed">Confirmed</span></div>
							<div class="appointment-cell appointment-actions"><button class="appointment-btn appointment-btn--reschedule" type="button"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10a6 6 0 1 1 1.76 4.24" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round"></path><polyline points="4 14 4 10 8 10" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>Reschedule</button></div>
						</div>

						<div class="appointment-row" role="row">
							<div class="appointment-cell"><span class="appointment-id">AP006</span></div>
							<div class="appointment-cell"><span class="appointment-donor__name">John Smith</span><span class="appointment-donor__meta">D001 &bull; O+</span></div>
							<div class="appointment-cell"><span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>Jan 10, 2026</span><span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>10:00 AM</span></div>
							<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>Lipa Medix</div>
							<div class="appointment-cell"><span class="appointment-badge appointment-badge--confirmed">Confirmed</span></div>
							<div class="appointment-cell appointment-actions"><button class="appointment-btn appointment-btn--reschedule" type="button"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10a6 6 0 1 1 1.76 4.24" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round"></path><polyline points="4 14 4 10 8 10" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>Reschedule</button></div>
						</div>

						<div class="appointment-row" role="row">
							<div class="appointment-cell"><span class="appointment-id">AP007</span></div>
							<div class="appointment-cell"><span class="appointment-donor__name">John Smith</span><span class="appointment-donor__meta">D001 &bull; O+</span></div>
							<div class="appointment-cell"><span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>Jan 10, 2026</span><span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>10:00 AM</span></div>
							<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>Lipa Medix</div>
							<div class="appointment-cell"><span class="appointment-badge appointment-badge--cancelled">Cancelled</span></div>
							<div class="appointment-cell appointment-actions"></div>
						</div>

						<div class="appointment-row" role="row">
							<div class="appointment-cell"><span class="appointment-id">AP008</span></div>
							<div class="appointment-cell"><span class="appointment-donor__name">John Smith</span><span class="appointment-donor__meta">D001 &bull; O+</span></div>
							<div class="appointment-cell"><span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>Jan 10, 2026</span><span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>10:00 AM</span></div>
							<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>Lipa Medix</div>
							<div class="appointment-cell"><span class="appointment-badge appointment-badge--confirmed">Confirmed</span></div>
							<div class="appointment-cell appointment-actions"><button class="appointment-btn appointment-btn--reschedule" type="button"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10a6 6 0 1 1 1.76 4.24" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round"></path><polyline points="4 14 4 10 8 10" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>Reschedule</button></div>
						</div>

						<div class="appointment-row" role="row">
							<div class="appointment-cell"><span class="appointment-id">AP009</span></div>
							<div class="appointment-cell"><span class="appointment-donor__name">John Smith</span><span class="appointment-donor__meta">D001 &bull; O+</span></div>
							<div class="appointment-cell"><span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>Jan 10, 2026</span><span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>10:00 AM</span></div>
							<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>Lipa Medix</div>
							<div class="appointment-cell"><span class="appointment-badge appointment-badge--pending">Pending</span></div>
							<div class="appointment-cell appointment-actions"><button class="appointment-btn appointment-btn--approve" type="button"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>Approve</button><button class="appointment-btn appointment-btn--reject" type="button"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="#b60c0c" stroke-width="1.8"></circle><line x1="15" y1="9" x2="9" y2="15" stroke="#b60c0c" stroke-width="1.8" stroke-linecap="round"></line><line x1="9" y1="9" x2="15" y2="15" stroke="#b60c0c" stroke-width="1.8" stroke-linecap="round"></line></svg>Reject</button></div>
						</div>
					</div>
				</div>

				<div class="appointment-pagination" aria-label="Pagination">
					<span class="appointment-pagination__info">Showing 1 to 9 of 9 donors</span>
					<div class="appointment-pagination__pages">
						<button class="appointment-page-btn appointment-page-btn--nav" type="button" aria-label="Previous page"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
						<button class="appointment-page-btn appointment-page-btn--active" type="button" aria-current="page">1</button>
						<button class="appointment-page-btn appointment-page-btn--nav" type="button" aria-label="Next page"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
					</div>
				</div>
			</section>
		</section>
	</main>
@endsection

@push('admin_scripts')
<script>
	(function () {
		document.querySelectorAll('.appointment-view-btn, .appointment-btn, .appointment-page-btn, a[href="#"]').forEach(function (element) {
			element.addEventListener('click', function (event) {
				event.preventDefault();
			});
		});
	})();
</script>
@endpush



