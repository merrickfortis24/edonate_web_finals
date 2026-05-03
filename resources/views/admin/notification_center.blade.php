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
	<button class="btn-send btn" type="button" id="notificationSendBtn">
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
	'notificationPayload' => $notificationPayload ?? [
		'api' => [
			'listUrl' => '',
			'storeUrl' => '',
			'detailBaseUrl' => '',
			'markReadBaseUrl' => '',
			'markAllReadUrl' => '',
			'deleteBaseUrl' => '',
			'clearAllUrl' => '',
		],
		'summary' => [
			'total' => 0,
			'unread' => 0,
			'read' => 0,
		],
		'filters' => [],
		'types' => [],
		'channels' => [],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	@php
		$summary = data_get($notificationPayload ?? [], 'summary', ['total' => 0, 'unread' => 0, 'read' => 0]);
		$filters = data_get($notificationPayload ?? [], 'filters', []);
		$types = data_get($notificationPayload ?? [], 'types', []);
		$channels = data_get($notificationPayload ?? [], 'channels', []);
	@endphp

	<main class="main container-fluid px-0">
		<div class="page-body container-fluid py-3">
			<section class="stats-row row g-3" role="region" aria-label="Notification statistics">
				<div class="col-6 col-md-4">
					<article class="stat-card stat-card--blue h-100">
						<span class="stat-card__label">Total Notifications</span>
						<span class="stat-card__value" id="notificationStatTotal">{{ (int) data_get($summary, 'total', 0) }}</span>
					</article>
				</div>
				<div class="col-6 col-md-4">
					<article class="stat-card stat-card--red h-100">
						<span class="stat-card__label">Unread</span>
						<span class="stat-card__value" id="notificationStatUnread">{{ (int) data_get($summary, 'unread', 0) }}</span>
					</article>
				</div>
				<div class="col-6 col-md-4">
					<article class="stat-card stat-card--green h-100">
						<span class="stat-card__label">Read</span>
						<span class="stat-card__value" id="notificationStatRead">{{ (int) data_get($summary, 'read', 0) }}</span>
					</article>
				</div>
			</section>

			<section class="toolbar d-flex align-items-center flex-wrap gap-2" role="toolbar" aria-label="Notification controls">
				<div class="toolbar__filter">
					<label class="toolbar__filter-btn" for="notificationFilterSelect" aria-label="Filter notifications">
						<svg class="filter-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<path d="M22 3H2L10 12.46V19L14 21V12.46L22 3Z" stroke="#333" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<select id="notificationFilterSelect" class="toolbar__filter-select" aria-label="Filter notifications">
							@forelse ($filters as $filter)
								<option value="{{ data_get($filter, 'value') }}">{{ data_get($filter, 'label') }}</option>
							@empty
								<option value="all">All Notifications</option>
							@endforelse
						</select>
						<svg class="toolbar__filter-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<path d="M6 9L12 15L18 9" stroke="#333" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</label>
				</div>

				<div class="toolbar__spacer"></div>

				<button class="btn-mark-all btn" type="button" id="notificationMarkAllReadBtn">Mark All as Read</button>

				<button class="btn-clear-all btn" type="button" id="notificationClearAllBtn">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<polyline points="3,6 5,6 21,6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M19 6L18.1 20.1C18 21.2 17.1 22 16 22H8C6.9 22 6 21.2 5.9 20.1L5 6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M9 6V4C9 3.4 9.4 3 10 3H14C14.6 3 15 3.4 15 4V6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					Clear All
				</button>
			</section>

			<section class="notif-list" id="notificationList" aria-label="Notifications">
				<div class="notification-state text-center text-muted py-4">Loading notifications...</div>
			</section>
		</div>
	</main>

	<div class="modal fade" id="notificationDetailModal" tabindex="-1" aria-labelledby="notificationDetailTitle" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<div class="modal-content notification-modal">
				<div class="modal-header">
					<h5 class="modal-title" id="notificationDetailTitle">Notification Details</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="notificationDetailBody">
					<div class="text-center text-muted py-4">Loading details...</div>
				</div>
				<div class="modal-footer">
					<a href="#" class="btn btn-outline-primary d-none" id="notificationRelatedLink">Open Related Record</a>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="notificationSendModal" tabindex="-1" aria-labelledby="notificationSendTitle" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<form class="modal-content notification-modal" id="notificationSendForm" novalidate>
				<div class="modal-header">
					<h5 class="modal-title" id="notificationSendTitle">Send Notification</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row g-3">
						<div class="col-12">
							<label for="notificationTitleField" class="form-label">Title</label>
							<input type="text" class="form-control" id="notificationTitleField" name="title" maxlength="150" required>
							<div class="invalid-feedback" id="notificationTitleError"></div>
						</div>
						<div class="col-12">
							<label for="notificationMessageField" class="form-label">Message</label>
							<textarea class="form-control" id="notificationMessageField" name="message" rows="4" maxlength="1000" required></textarea>
							<div class="invalid-feedback" id="notificationMessageError"></div>
						</div>
						<div class="col-12 col-md-6">
							<label for="notificationTypeField" class="form-label">Type/category</label>
							<select class="form-select" id="notificationTypeField" name="type" required>
								@forelse ($types as $type)
									<option value="{{ data_get($type, 'value') }}">{{ data_get($type, 'label') }}</option>
								@empty
									<option value="system">System</option>
								@endforelse
							</select>
						</div>
						<div class="col-12 col-md-6">
							<label for="notificationChannelField" class="form-label">Channel</label>
							<select class="form-select" id="notificationChannelField" name="channel" required>
								@forelse ($channels as $channel)
									<option value="{{ data_get($channel, 'value') }}">{{ data_get($channel, 'label') }}</option>
								@empty
									<option value="system">System</option>
								@endforelse
							</select>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="notificationSubmitBtn">Send Notification</button>
				</div>
			</form>
		</div>
	</div>
@endsection

@push('admin_scripts')
	<script src="{{ asset('js/admin/notification-center.js') }}"></script>
@endpush
