@extends('layouts.admin')

@section('title', 'eDonate - Audit Logs')
@section('admin_page_class', 'admin-audit-logs-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('render_default_hamburger', 'false')

@section('header_title', 'Audit Logs')
@section('header_subtitle', 'Monitor and review all system activities and user actions')

@section('header_slot')
	<button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
	</button>
@endsection

@section('header_actions')
	<button class="audit-export-btn btn" type="button" aria-label="Export logs">
		<svg class="audit-export-btn__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15" stroke="white" stroke-width="2" stroke-linecap="round"/>
			<path d="M7 10L12 15L17 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			<path d="M12 15V3" stroke="white" stroke-width="2" stroke-linecap="round"/>
		</svg>
		Export Logs
	</button>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page' => 'audit-logs',
	'auditLogs' => [
		'filters' => [
			'actions' => [
				['value' => '', 'label' => 'All Actions'],
				['value' => 'approve', 'label' => 'Approve'],
				['value' => 'create', 'label' => 'Create'],
				['value' => 'export', 'label' => 'Export'],
				['value' => 'update', 'label' => 'Update'],
				['value' => 'login', 'label' => 'Login'],
				['value' => 'delete', 'label' => 'Delete'],
				['value' => 'view', 'label' => 'View'],
			],
			'users' => [
				['value' => '', 'label' => 'All Users'],
				['value' => 'admin', 'label' => 'Admin'],
				['value' => 'donor', 'label' => 'Donor'],
				['value' => 'system', 'label' => 'System'],
			],
		],
		'entries' => [
			[
				'timestamp' => '2026-02-04 14:32:15',
				'userName' => 'Admin User',
				'userRole' => 'Admin',
				'userType' => 'admin',
				'actionType' => 'approve',
				'actionLabel' => 'Approve',
				'description' => 'Approved donor registration',
				'moduleType' => 'user-mgmt',
				'moduleLabel' => 'User Management',
				'ipAddress' => '192.168.1.100',
				'result' => 'success',
				'resultLabel' => 'Success',
			],
			[
				'timestamp' => '2026-02-04 14:28:03',
				'userName' => 'Sarah Johnson',
				'userRole' => 'Donor',
				'userType' => 'donor',
				'actionType' => 'create',
				'actionLabel' => 'Create',
				'description' => 'Booked appointment',
				'moduleType' => 'appointments',
				'moduleLabel' => 'Appointments',
				'ipAddress' => '203.123.45.67',
				'result' => 'success',
				'resultLabel' => 'Success',
			],
			[
				'timestamp' => '2026-02-04 14:15:42',
				'userName' => 'Admin User',
				'userRole' => 'Admin',
				'userType' => 'admin',
				'actionType' => 'export',
				'actionLabel' => 'Export',
				'description' => 'Exported donation records',
				'moduleType' => 'reports',
				'moduleLabel' => 'Reports',
				'ipAddress' => '192.168.1.100',
				'result' => 'success',
				'resultLabel' => 'Success',
			],
			[
				'timestamp' => '2026-02-04 14:05:21',
				'userName' => 'System',
				'userRole' => 'System',
				'userType' => 'system',
				'actionType' => 'create',
				'actionLabel' => 'Create',
				'description' => 'Automated backup completed',
				'moduleType' => 'settings',
				'moduleLabel' => 'Settings',
				'ipAddress' => '127.0.0.1',
				'result' => 'success',
				'resultLabel' => 'Success',
			],
			[
				'timestamp' => '2026-02-04 13:58:16',
				'userName' => 'Admin User',
				'userRole' => 'Admin',
				'userType' => 'admin',
				'actionType' => 'update',
				'actionLabel' => 'Update',
				'description' => 'Updated donor profile',
				'moduleType' => 'user-mgmt',
				'moduleLabel' => 'User Management',
				'ipAddress' => '192.168.1.100',
				'result' => 'success',
				'resultLabel' => 'Success',
			],
			[
				'timestamp' => '2026-02-04 13:45:33',
				'userName' => 'Robert Chen',
				'userRole' => 'Donor',
				'userType' => 'donor',
				'actionType' => 'login',
				'actionLabel' => 'Login',
				'description' => 'Failed login attempt',
				'moduleType' => 'auth',
				'moduleLabel' => 'Authentication',
				'ipAddress' => '187.45.67.89',
				'result' => 'failed',
				'resultLabel' => 'Failed',
			],
			[
				'timestamp' => '2026-02-04 13:40:12',
				'userName' => 'Admin User',
				'userRole' => 'Admin',
				'userType' => 'admin',
				'actionType' => 'delete',
				'actionLabel' => 'Delete',
				'description' => 'Deleted cancelled appointment',
				'moduleType' => 'appointments',
				'moduleLabel' => 'Appointments',
				'ipAddress' => '192.168.1.100',
				'result' => 'success',
				'resultLabel' => 'Success',
			],
			[
				'timestamp' => '2026-02-04 13:25:47',
				'userName' => 'Admin User',
				'userRole' => 'Admin',
				'userType' => 'admin',
				'actionType' => 'view',
				'actionLabel' => 'View',
				'description' => 'Viewed blood availability map',
				'moduleType' => 'blood-map',
				'moduleLabel' => 'Blood Map',
				'ipAddress' => '192.168.1.100',
				'result' => 'warning',
				'resultLabel' => 'Warning',
			],
			[
				'timestamp' => '2026-02-04 13:12:05',
				'userName' => 'Emily Rodriguez',
				'userRole' => 'Donor',
				'userType' => 'donor',
				'actionType' => 'view',
				'actionLabel' => 'View',
				'description' => 'Completed eligibility check',
				'moduleType' => 'user-mgmt',
				'moduleLabel' => 'User Management',
				'ipAddress' => '180.95.23.11',
				'result' => 'success',
				'resultLabel' => 'Success',
			],
			[
				'timestamp' => '2026-02-04 13:05:28',
				'userName' => 'System',
				'userRole' => 'System',
				'userType' => 'system',
				'actionType' => 'create',
				'actionLabel' => 'Create',
				'description' => 'Sent appointment reminders',
				'moduleType' => 'notifications',
				'moduleLabel' => 'Notifications',
				'ipAddress' => '127.0.0.1',
				'result' => 'success',
				'resultLabel' => 'Success',
			],
		],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	<main class="main container-fluid px-0">
		<div class="page-body container-fluid py-3">
			<section class="audit-stats row g-3" aria-label="Audit statistics">
				<div class="col-12 col-sm-6 col-xl-3">
					<article class="audit-stat-card audit-stat-card--green h-100">
						<p class="audit-stat-card__label">Total Logs</p>
						<p class="audit-stat-card__value" id="auditStatTotal">0</p>
					</article>
				</div>
				<div class="col-12 col-sm-6 col-xl-3">
					<article class="audit-stat-card audit-stat-card--gold h-100">
						<p class="audit-stat-card__label">Success</p>
						<p class="audit-stat-card__value" id="auditStatSuccess">0</p>
					</article>
				</div>
				<div class="col-12 col-sm-6 col-xl-3">
					<article class="audit-stat-card audit-stat-card--red h-100">
						<p class="audit-stat-card__label">Failed</p>
						<p class="audit-stat-card__value" id="auditStatFailed">0</p>
					</article>
				</div>
				<div class="col-12 col-sm-6 col-xl-3">
					<article class="audit-stat-card audit-stat-card--blue h-100">
						<p class="audit-stat-card__label">Warnings</p>
						<p class="audit-stat-card__value" id="auditStatWarnings">0</p>
					</article>
				</div>
			</section>

			<section class="audit-filter-card row g-3 align-items-center" role="search" aria-label="Audit log filters">
				<div class="col-12 col-lg">
					<div class="audit-search-wrap">
						<span class="audit-search-wrap__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
								<line x1="21" y1="21" x2="16.65" y2="16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
						</span>
						<input type="text" class="form-control audit-search-input" id="auditSearchInput" placeholder="Search by date, user, or action..." aria-label="Search logs">
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-3">
					<div class="audit-select-wrap">
						<select class="form-select audit-filter-select" id="auditActionFilter" aria-label="Filter by action"></select>
						<span class="audit-select-wrap__chevron" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<polyline points="6 9 12 15 18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</span>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-3">
					<div class="audit-select-wrap">
						<select class="form-select audit-filter-select" id="auditUserFilter" aria-label="Filter by user"></select>
						<span class="audit-select-wrap__chevron" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<polyline points="6 9 12 15 18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</span>
					</div>
				</div>
			</section>

			<section class="audit-table-card" aria-label="Activity logs">
				<div class="audit-table-card__header d-flex align-items-center justify-content-between gap-3">
					<h2 class="audit-table-card__title mb-0">Activity Logs</h2>
					<p class="audit-table-card__meta mb-0" id="auditTableMeta">Showing 0 of 0 entries</p>
				</div>

				<div class="table-responsive">
					<table class="table audit-table align-middle mb-0" aria-label="Audit activity logs table">
						<thead>
							<tr>
								<th scope="col">Date &amp; Time</th>
								<th scope="col">User</th>
								<th scope="col">Action</th>
								<th scope="col" class="audit-col-module">Module</th>
								<th scope="col" class="audit-col-ip">IP Address</th>
								<th scope="col">Result</th>
								<th scope="col">Actions</th>
							</tr>
						</thead>
						<tbody id="auditTableBody"></tbody>
					</table>
				</div>
			</section>
		</div>
	</main>
@endsection

@push('admin_scripts')
<script>
	(function () {
		var payload = window.AdminPageData && window.AdminPageData.auditLogs ? window.AdminPageData.auditLogs : {};
		var entries = Array.isArray(payload.entries) ? payload.entries.slice() : [];
		var filters = payload.filters || {};

		var searchInput = document.getElementById('auditSearchInput');
		var actionFilter = document.getElementById('auditActionFilter');
		var userFilter = document.getElementById('auditUserFilter');
		var tableBody = document.getElementById('auditTableBody');
		var tableMeta = document.getElementById('auditTableMeta');

		var statTotal = document.getElementById('auditStatTotal');
		var statSuccess = document.getElementById('auditStatSuccess');
		var statFailed = document.getElementById('auditStatFailed');
		var statWarnings = document.getElementById('auditStatWarnings');

		function escapeHtml(value) {
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		function populateSelect(selectElement, options, fallbackLabel) {
			if (!selectElement) {
				return;
			}

			var list = Array.isArray(options) && options.length
				? options
				: [{ value: '', label: fallbackLabel }];

			selectElement.innerHTML = list.map(function (option) {
				return '<option value="' + escapeHtml(option.value) + '">' + escapeHtml(option.label) + '</option>';
			}).join('');
		}

		function renderStats(items) {
			var total = items.length;
			var successCount = 0;
			var failedCount = 0;
			var warningCount = 0;

			for (var i = 0; i < items.length; i += 1) {
				var result = String(items[i].result || '').toLowerCase();
				if (result === 'success') {
					successCount += 1;
				} else if (result === 'failed') {
					failedCount += 1;
				} else if (result === 'warning') {
					warningCount += 1;
				}
			}

			if (statTotal) {
				statTotal.textContent = String(total);
			}
			if (statSuccess) {
				statSuccess.textContent = String(successCount);
			}
			if (statFailed) {
				statFailed.textContent = String(failedCount);
			}
			if (statWarnings) {
				statWarnings.textContent = String(warningCount);
			}
		}

		function getUserAvatarIcon(userType) {
			if (userType === 'system') {
				return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5 3.5 3.5 0 0 1-3.5 3.5m7.43-2.92c.04-.33.07-.67.07-1.08s-.03-.74-.07-1.08l2.32-1.82c.21-.16.27-.45.13-.69l-2.2-3.8c-.13-.23-.43-.31-.67-.23l-2.73 1.1c-.57-.43-1.17-.8-1.84-1.07L14.5 2.42c-.04-.26-.27-.42-.5-.42h-4c-.23 0-.46.16-.5.42l-.41 2.9c-.66.27-1.27.63-1.84 1.07L4.52 5.3c-.24-.09-.54 0-.67.23l-2.2 3.8c-.14.24-.07.53.13.69l2.32 1.82c-.04.34-.07.67-.07 1.08s.03.74.07 1.08l-2.32 1.82c-.21.16-.27.45-.13.69l2.2 3.8c.13.23.43.31.67.23l2.73-1.1c.57.43 1.17.8 1.84 1.07l.41 2.9c.04.26.27.42.5.42h4c.23 0 .46-.16.5-.42l.41-2.9c.66-.27 1.27-.63 1.84-1.07l2.73 1.1c.24.09.54 0 .67-.23l2.2-3.8c.14-.24.07-.53-.13-.69l-2.32-1.82z"/></svg>';
			}

			return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>';
		}

		function getResultIcon(result) {
			if (result === 'failed') {
				return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
			}
			if (result === 'warning') {
				return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2L2 20h20L12 2z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1"/></svg>';
			}

			return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
		}

		function renderRows(items) {
			if (!tableBody) {
				return;
			}

			if (!items.length) {
				tableBody.innerHTML = '<tr><td colspan="7" class="audit-table__empty">No log entries found.</td></tr>';
				if (tableMeta) {
					tableMeta.textContent = 'Showing 0 of ' + entries.length + ' entries';
				}
				return;
			}

			tableBody.innerHTML = items.map(function (entry) {
				var userType = String(entry.userType || '').toLowerCase();
				var actionType = String(entry.actionType || '').toLowerCase();
				var moduleType = String(entry.moduleType || '').toLowerCase();
				var resultType = String(entry.result || '').toLowerCase();

				var userAvatarClass = 'audit-user__avatar--admin';
				if (userType === 'donor') {
					userAvatarClass = 'audit-user__avatar--donor';
				} else if (userType === 'system') {
					userAvatarClass = 'audit-user__avatar--system';
				}

				return '<tr>' +
					'<td><div class="audit-datetime">' +
					'<span class="audit-datetime__icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>' +
					'<span class="audit-datetime__text">' + escapeHtml(entry.timestamp) + '</span>' +
					'</div></td>' +
					'<td><div class="audit-user">' +
					'<span class="audit-user__avatar ' + userAvatarClass + '" aria-hidden="true">' + getUserAvatarIcon(userType) + '</span>' +
					'<span class="audit-user__meta"><span class="audit-user__name">' + escapeHtml(entry.userName) + '</span><span class="audit-user__role">' + escapeHtml(entry.userRole) + '</span></span>' +
					'</div></td>' +
					'<td><div class="audit-action"><span class="audit-action__desc">' + escapeHtml(entry.description) + '</span>' +
					'<span class="audit-action-badge audit-action-badge--' + escapeHtml(actionType) + '">' + escapeHtml(entry.actionLabel) + '</span></div></td>' +
					'<td class="audit-col-module"><span class="audit-module-chip audit-module-chip--' + escapeHtml(moduleType) + '">' + escapeHtml(entry.moduleLabel) + '</span></td>' +
					'<td class="audit-col-ip"><span class="audit-ip-text">' + escapeHtml(entry.ipAddress) + '</span></td>' +
					'<td><span class="audit-result-pill audit-result-pill--' + escapeHtml(resultType) + '">' + getResultIcon(resultType) + escapeHtml(entry.resultLabel) + '</span></td>' +
					'<td><button class="audit-view-link btn btn-link p-0" type="button" aria-label="View log details for ' + escapeHtml(entry.userName) + '"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>View</button></td>' +
					'</tr>';
			}).join('');

			if (tableMeta) {
				tableMeta.textContent = 'Showing ' + items.length + ' of ' + entries.length + ' entries';
			}
		}

		function filterRows() {
			var keyword = searchInput ? searchInput.value.trim().toLowerCase() : '';
			var actionValue = actionFilter ? actionFilter.value : '';
			var userValue = userFilter ? userFilter.value : '';

			var filtered = entries.filter(function (entry) {
				var matchesKeyword = true;
				if (keyword) {
					var searchPool = [
						entry.timestamp,
						entry.userName,
						entry.userRole,
						entry.description,
						entry.moduleLabel,
						entry.ipAddress,
					].join(' ').toLowerCase();

					matchesKeyword = searchPool.indexOf(keyword) !== -1;
				}

				var matchesAction = !actionValue || entry.actionType === actionValue;
				var matchesUser = !userValue || entry.userType === userValue;

				return matchesKeyword && matchesAction && matchesUser;
			});

			renderStats(filtered);
			renderRows(filtered);
		}

		populateSelect(actionFilter, filters.actions, 'All Actions');
		populateSelect(userFilter, filters.users, 'All Users');

		filterRows();

		if (searchInput) {
			searchInput.addEventListener('input', filterRows);
		}
		if (actionFilter) {
			actionFilter.addEventListener('change', filterRows);
		}
		if (userFilter) {
			userFilter.addEventListener('change', filterRows);
		}
	})();
</script>
@endpush
