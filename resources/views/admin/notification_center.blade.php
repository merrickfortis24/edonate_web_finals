@extends('layouts.admin')

@section('title', 'eDonate - Notification Center')
@section('admin_page_class', 'admin-notification-center-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('sidebar_open_class', 'is-open')
@section('render_default_hamburger', 'false')

@section('header_title', 'Notification Center')
@section('header_subtitle', 'Manage email and push notifications for confirmations and updates')

@section('header_slot')
	<button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
	</button>
@endsection

@section('header_actions')
	<button class="btn-send btn" type="button">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M22 2L11 13" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			<path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
		Send Notifications
	</button>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'notification-center',
	'summary' => [
		'total' => 9,
		'unread' => 6,
		'read' => 3,
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('content')
	<main class="main container-fluid px-0">
	<div class="page-body container-fluid py-3">
		<section class="stats-row row g-3" role="region" aria-label="Notification statistics">
			<div class="col-6 col-md-4">
				<article class="stat-card stat-card--blue h-100">
					<span class="stat-card__label">Total Notifications</span>
					<span class="stat-card__value">9</span>
				</article>
			</div>
			<div class="col-6 col-md-4">
				<article class="stat-card stat-card--red h-100">
					<span class="stat-card__label">Unread</span>
					<span class="stat-card__value">6</span>
				</article>
			</div>
			<div class="col-6 col-md-4">
				<article class="stat-card stat-card--green h-100">
					<span class="stat-card__label">Read</span>
					<span class="stat-card__value">3</span>
				</article>
			</div>
		</section>

		<section class="toolbar d-flex align-items-center flex-wrap gap-2" role="toolbar" aria-label="Notification controls">
			<div class="toolbar__filter">
				<button class="toolbar__filter-btn" type="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Filter notifications">
					<svg class="filter-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M22 3H2L10 12.46V19L14 21V12.46L22 3Z" stroke="#333" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<span class="toolbar__filter-label">All Notifications</span>
					<svg class="toolbar__filter-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M6 9L12 15L18 9" stroke="#333" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>

			<div class="toolbar__spacer"></div>

			<button class="btn-mark-all btn" type="button">Mark All as Read</button>

			<button class="btn-clear-all btn" type="button">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<polyline points="3,6 5,6 21,6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M19 6L18.1 20.1C18 21.2 17.1 22 16 22H8C6.9 22 6 21.2 5.9 20.1L5 6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M9 6V4C9 3.4 9.4 3 10 3H14C14.6 3 15 3.4 15 4V6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
				Clear All
			</button>
		</section>

		<section class="notif-list" aria-label="Notifications">
			<article class="notif-item">
				<div class="notif-item__icon notif-item__icon--green" aria-label="Donor registration icon">
					<svg width="25" height="25" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<circle cx="12" cy="12" r="9" stroke="#129800" stroke-width="1.8"/>
						<path d="M8.5 12L11 14.5L15.5 9.5" stroke="#129800" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<div class="notif-item__body">
					<div class="notif-item__title-row">
						<span class="notif-item__title">New Donor Registration</span>
						<span class="badge-new" role="status" aria-label="New notification">New</span>
					</div>
					<p class="notif-item__desc">Alice Guo has successfully registered as a new donor.</p>
					<div class="notif-item__actions">
						<button class="btn-mark-read" type="button" aria-label="Mark New Donor Registration as read">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<circle cx="12" cy="12" r="9" stroke="#0063aa" stroke-width="1.8"/>
								<path d="M8.5 12L11 14.5L15.5 9.5" stroke="#0063aa" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							Mark as Read
						</button>
						<button class="btn-view-details" type="button" aria-label="View details for New Donor Registration">View Details</button>
						<button class="btn-delete" type="button" aria-label="Delete New Donor Registration notification">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<polyline points="3,6 5,6 21,6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M19 6L18.1 20.1C18 21.2 17.1 22 16 22H8C6.9 22 6 21.2 5.9 20.1L5 6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M9 6V4C9 3.4 9.4 3 10 3H14C14.6 3 15 3.4 15 4V6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					</div>
				</div>
				<time class="notif-item__time" datetime="">5 minutes ago</time>
			</article>

			<article class="notif-item">
				<div class="notif-item__icon notif-item__icon--yellow" aria-label="Appointment cancellation icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#b8960c" stroke-width="1.8"/>
						<path d="M12 8V12" stroke="#b8960c" stroke-width="2" stroke-linecap="round"/>
						<circle cx="12" cy="16" r="0.8" fill="#b8960c" stroke="#b8960c" stroke-width="0.5"/>
					</svg>
				</div>
				<div class="notif-item__body">
					<div class="notif-item__title-row">
						<span class="notif-item__title">Appointment Cancellation</span>
						<span class="badge-new" role="status" aria-label="New notification">New</span>
					</div>
					<p class="notif-item__desc">Mike Tyson cancelled his appointment scheduled for October 25.</p>
					<div class="notif-item__actions">
						<button class="btn-mark-read" type="button" aria-label="Mark Appointment Cancellation as read">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<circle cx="12" cy="12" r="9" stroke="#0063aa" stroke-width="1.8"/>
								<path d="M8.5 12L11 14.5L15.5 9.5" stroke="#0063aa" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							Mark as Read
						</button>
						<button class="btn-view-details" type="button" aria-label="View details for Appointment Cancellation">View Details</button>
						<button class="btn-delete" type="button" aria-label="Delete Appointment Cancellation notification">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<polyline points="3,6 5,6 21,6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M19 6L18.1 20.1C18 21.2 17.1 22 16 22H8C6.9 22 6 21.2 5.9 20.1L5 6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M9 6V4C9 3.4 9.4 3 10 3H14C14.6 3 15 3.4 15 4V6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					</div>
				</div>
				<time class="notif-item__time" datetime="">5 minutes ago</time>
			</article>

			<article class="notif-item">
				<div class="notif-item__icon notif-item__icon--blue" aria-label="Appointment rescheduled icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#0063aa" stroke-width="1.8"/>
						<path d="M12 16V12" stroke="#0063aa" stroke-width="2" stroke-linecap="round"/>
						<circle cx="12" cy="8.5" r="0.8" fill="#0063aa" stroke="#0063aa" stroke-width="0.5"/>
					</svg>
				</div>
				<div class="notif-item__body">
					<div class="notif-item__title-row">
						<span class="notif-item__title">Appointment Rescheduled</span>
						<span class="badge-new" role="status" aria-label="New notification">New</span>
					</div>
					<p class="notif-item__desc">Alice Guo has successfully registered as a new donor.</p>
					<div class="notif-item__actions">
						<button class="btn-mark-read" type="button" aria-label="Mark Appointment Rescheduled as read">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<circle cx="12" cy="12" r="9" stroke="#0063aa" stroke-width="1.8"/>
								<path d="M8.5 12L11 14.5L15.5 9.5" stroke="#0063aa" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							Mark as Read
						</button>
						<button class="btn-view-details" type="button" aria-label="View details for Appointment Rescheduled">View Details</button>
						<button class="btn-delete" type="button" aria-label="Delete Appointment Rescheduled notification">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<polyline points="3,6 5,6 21,6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M19 6L18.1 20.1C18 21.2 17.1 22 16 22H8C6.9 22 6 21.2 5.9 20.1L5 6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M9 6V4C9 3.4 9.4 3 10 3H14C14.6 3 15 3.4 15 4V6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					</div>
				</div>
				<time class="notif-item__time" datetime="">5 minutes ago</time>
			</article>

			<article class="notif-item">
				<div class="notif-item__icon notif-item__icon--green" aria-label="Donation completed icon">
					<svg width="25" height="25" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<circle cx="12" cy="12" r="9" stroke="#129800" stroke-width="1.8"/>
						<path d="M8.5 12L11 14.5L15.5 9.5" stroke="#129800" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<div class="notif-item__body">
					<div class="notif-item__title-row">
						<span class="notif-item__title">Donation Completed</span>
					</div>
					<p class="notif-item__desc">Alice Guo has successfully registered as a new donor.</p>
					<div class="notif-item__actions">
						<button class="btn-mark-read" type="button" aria-label="Mark Donation Completed as read">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<circle cx="12" cy="12" r="9" stroke="#0063aa" stroke-width="1.8"/>
								<path d="M8.5 12L11 14.5L15.5 9.5" stroke="#0063aa" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							Mark as Read
						</button>
						<button class="btn-view-details" type="button" aria-label="View details for Donation Completed">View Details</button>
						<button class="btn-delete" type="button" aria-label="Delete Donation Completed notification">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<polyline points="3,6 5,6 21,6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M19 6L18.1 20.1C18 21.2 17.1 22 16 22H8C6.9 22 6 21.2 5.9 20.1L5 6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M9 6V4C9 3.4 9.4 3 10 3H14C14.6 3 15 3.4 15 4V6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					</div>
				</div>
				<time class="notif-item__time" datetime="">5 minutes ago</time>
			</article>

			<article class="notif-item">
				<div class="notif-item__icon notif-item__icon--yellow" aria-label="Low blood stock alert icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#b8960c" stroke-width="1.8"/>
						<path d="M12 8V12" stroke="#b8960c" stroke-width="2" stroke-linecap="round"/>
						<circle cx="12" cy="16" r="0.8" fill="#b8960c" stroke="#b8960c" stroke-width="0.5"/>
					</svg>
				</div>
				<div class="notif-item__body">
					<div class="notif-item__title-row">
						<span class="notif-item__title">Low Blood Stock Alert</span>
					</div>
					<p class="notif-item__desc">Alice Guo has successfully registered as a new donor.</p>
					<div class="notif-item__actions">
						<button class="btn-mark-read" type="button" aria-label="Mark Low Blood Stock Alert as read">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<circle cx="12" cy="12" r="9" stroke="#0063aa" stroke-width="1.8"/>
								<path d="M8.5 12L11 14.5L15.5 9.5" stroke="#0063aa" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							Mark as Read
						</button>
						<button class="btn-view-details" type="button" aria-label="View details for Low Blood Stock Alert">View Details</button>
						<button class="btn-delete" type="button" aria-label="Delete Low Blood Stock Alert notification">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<polyline points="3,6 5,6 21,6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M19 6L18.1 20.1C18 21.2 17.1 22 16 22H8C6.9 22 6 21.2 5.9 20.1L5 6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M9 6V4C9 3.4 9.4 3 10 3H14C14.6 3 15 3.4 15 4V6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					</div>
				</div>
				<time class="notif-item__time" datetime="">5 minutes ago</time>
			</article>

			<article class="notif-item">
				<div class="notif-item__icon notif-item__icon--blue" aria-label="Monthly report generated icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#0063aa" stroke-width="1.8"/>
						<path d="M12 16V12" stroke="#0063aa" stroke-width="2" stroke-linecap="round"/>
						<circle cx="12" cy="8.5" r="0.8" fill="#0063aa" stroke="#0063aa" stroke-width="0.5"/>
					</svg>
				</div>
				<div class="notif-item__body">
					<div class="notif-item__title-row">
						<span class="notif-item__title">Monthly Report Generated</span>
					</div>
					<p class="notif-item__desc">Alice Guo has successfully registered as a new donor.</p>
					<div class="notif-item__actions">
						<button class="btn-mark-read" type="button" aria-label="Mark Monthly Report Generated as read">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<circle cx="12" cy="12" r="9" stroke="#0063aa" stroke-width="1.8"/>
								<path d="M8.5 12L11 14.5L15.5 9.5" stroke="#0063aa" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							Mark as Read
						</button>
						<button class="btn-view-details" type="button" aria-label="View details for Monthly Report Generated">View Details</button>
						<button class="btn-delete" type="button" aria-label="Delete Monthly Report Generated notification">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<polyline points="3,6 5,6 21,6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M19 6L18.1 20.1C18 21.2 17.1 22 16 22H8C6.9 22 6 21.2 5.9 20.1L5 6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M9 6V4C9 3.4 9.4 3 10 3H14C14.6 3 15 3.4 15 4V6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					</div>
				</div>
				<time class="notif-item__time" datetime="">5 minutes ago</time>
			</article>
		</section>
	</div>
</main>
@endsection
